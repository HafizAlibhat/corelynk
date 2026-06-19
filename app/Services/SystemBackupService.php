<?php

namespace App\Services;

use App\Models\SystemBackupJobModel;
use App\Models\SystemBackupScheduleModel;
use DateInterval;
use DateTimeImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class SystemBackupService
{
    private SystemBackupJobModel $jobModel;
    private SystemBackupScheduleModel $scheduleModel;
    private string $appRoot;
    private string $backupRoot;

    public function __construct()
    {
        $this->jobModel = new SystemBackupJobModel();
        $this->scheduleModel = new SystemBackupScheduleModel();
        $this->appRoot = rtrim((string) (realpath(ROOTPATH) ?: ROOTPATH), '\\/');
        $this->backupRoot = rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'backups';
        $this->ensureBackupDirectories();
    }

    public function createManualBackup(string $backupType = 'full', ?int $initiatedBy = null, ?int $scheduleId = null): array
    {
        $backupType = $this->normalizeBackupType($backupType);
        $publicId = $this->uuid4();
        $dbName = $this->getDefaultDbConfig()['database'] ?? null;

        $jobId = $this->jobModel->insert([
            'public_id' => $publicId,
            'job_type' => $scheduleId ? 'scheduled' : 'manual',
            'backup_type' => $backupType,
            'status' => 'queued',
            'environment_name' => 'production',
            'app_root' => $this->appRoot,
            'db_name' => $dbName,
            'schedule_id' => $scheduleId,
            'initiated_by' => $initiatedBy,
            'health_status' => 'pending',
        ], true);

        if (!$jobId) {
            throw new RuntimeException('Unable to create backup job record.');
        }

        return $this->processJob((int) $jobId);
    }

    public function processDueSchedules(): int
    {
        $processed = 0;
        $now = new DateTimeImmutable('now');
        $due = $this->scheduleModel
            ->where('is_active', 1)
            ->groupStart()
                ->where('next_run_at IS NULL')
                ->orWhere('next_run_at <=', $now->format('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($due as $schedule) {
            $this->createManualBackup((string) ($schedule['backup_type'] ?? 'full'), (int) ($schedule['created_by'] ?? 0) ?: null, (int) $schedule['id']);
            $nextRunAt = $this->calculateNextRunAt($schedule, $now);
            $this->scheduleModel->update((int) $schedule['id'], [
                'last_run_at' => $now->format('Y-m-d H:i:s'),
                'next_run_at' => $nextRunAt,
            ]);
            $this->pruneScheduleBackups((int) $schedule['id'], (int) ($schedule['retention_count'] ?? 5));
            $processed++;
        }

        return $processed;
    }

    public function verifyBackup(string $publicId): array
    {
        $job = $this->jobModel->where('public_id', $publicId)->first();
        if (!$job) {
            throw new RuntimeException('Backup job not found.');
        }

        if (empty($job['archive_path']) || !is_file((string) $job['archive_path'])) {
            throw new RuntimeException('Backup archive file is missing.');
        }

        $health = $this->performHealthChecks((string) $job['archive_path'], (string) ($job['manifest_path'] ?? ''));
        $archiveSha = hash_file('sha256', (string) $job['archive_path']);

        $this->jobModel->update((int) $job['id'], [
            'archive_sha256' => $archiveSha,
            'archive_size_bytes' => filesize((string) $job['archive_path']) ?: 0,
            'health_status' => $health['health_status'],
            'health_details_json' => json_encode($health, JSON_UNESCAPED_SLASHES),
        ]);

        return $this->jobModel->find((int) $job['id']) ?? [];
    }

    public function restoreBackup(string $publicId, string $restoreMode = 'db_only', ?int $initiatedBy = null): array
    {
        $restoreMode = strtolower(trim($restoreMode));
        if (!in_array($restoreMode, ['db_only', 'full'], true)) {
            throw new RuntimeException('Unsupported restore mode.');
        }

        $job = $this->jobModel->where('public_id', $publicId)->first();
        if (!$job) {
            throw new RuntimeException('Backup job not found.');
        }
        if (($job['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Only completed backups can be restored.');
        }
        if (empty($job['archive_path']) || !is_file((string) $job['archive_path'])) {
            throw new RuntimeException('Backup archive file is missing.');
        }
        if ($restoreMode === 'full' && (string) ($job['backup_type'] ?? '') !== 'full') {
            throw new RuntimeException('Full restore requires a full backup archive.');
        }

        $verification = $this->performHealthChecks((string) $job['archive_path'], (string) ($job['manifest_path'] ?? ''));
        if (($verification['health_status'] ?? 'failed') === 'failed') {
            throw new RuntimeException('Backup health verification failed. Re-verify the archive before restoring it.');
        }

        $safetyBackup = $this->createManualBackup($restoreMode === 'full' ? 'full' : 'db_only', $initiatedBy, null);
        $restoreRoot = $this->backupRoot . DIRECTORY_SEPARATOR . 'working' . DIRECTORY_SEPARATOR . 'restore_' . $this->uuid4();
        $this->ensureDirectory($restoreRoot);

        try {
            $sqlPath = $this->extractDatabaseDumpFromArchive((string) $job['archive_path'], $restoreRoot);
            $restoreSummary = [
                'backup_public_id' => $publicId,
                'restore_mode' => $restoreMode,
                'safety_backup_public_id' => $safetyBackup['public_id'] ?? null,
            ];

            if ($sqlPath === null) {
                throw new RuntimeException('Database dump not found in backup archive.');
            }

            $this->restoreDatabaseDump($sqlPath);
            $restoreSummary['database_restored'] = true;

            if ($restoreMode === 'full') {
                $restoreSummary['application_files_restored'] = $this->restoreApplicationFromArchive((string) $job['archive_path']);
            }

            return $restoreSummary;
        } finally {
            $this->deleteDirectory($restoreRoot);
        }
    }

    public function calculateNextRunAt(array $schedule, ?DateTimeImmutable $from = null): ?string
    {
        $from = $from ?? new DateTimeImmutable('now');
        $frequency = strtolower((string) ($schedule['frequency_type'] ?? 'daily'));
        $timeOfDay = (string) ($schedule['time_of_day'] ?? '02:00');

        if ($frequency === 'interval') {
            $intervalMinutes = max(5, (int) ($schedule['interval_minutes'] ?? 60));
            return $from->add(new DateInterval('PT' . $intervalMinutes . 'M'))->format('Y-m-d H:i:s');
        }

        [$hour, $minute] = array_pad(array_map('intval', explode(':', $timeOfDay)), 2, 0);
        $candidate = $from->setTime($hour, $minute, 0);

        if ($frequency === 'weekly') {
            $day = max(0, min(6, (int) ($schedule['day_of_week'] ?? 1)));
            $currentDay = (int) $candidate->format('w');
            $offset = ($day - $currentDay + 7) % 7;
            if ($offset === 0 && $candidate <= $from) {
                $offset = 7;
            }
            return $candidate->add(new DateInterval('P' . $offset . 'D'))->format('Y-m-d H:i:s');
        }

        if ($candidate <= $from) {
            $candidate = $candidate->add(new DateInterval('P1D'));
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    private function processJob(int $jobId): array
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            throw new RuntimeException('Backup job record not found.');
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $timestamp = date('Ymd_His');
        $publicId = (string) $job['public_id'];
        $jobRoot = $this->backupRoot . DIRECTORY_SEPARATOR . 'working' . DIRECTORY_SEPARATOR . $publicId;
        $dbDir = $jobRoot . DIRECTORY_SEPARATOR . 'database';
        $metaDir = $jobRoot . DIRECTORY_SEPARATOR . 'meta';
        $archiveDir = $this->backupRoot . DIRECTORY_SEPARATOR . 'archives';
        $archiveBaseName = sprintf('corelynk_%s_%s', (string) $job['backup_type'], $timestamp);
        $archiveName = $archiveBaseName . '.zip';
        $archivePath = $archiveDir . DIRECTORY_SEPARATOR . $archiveName;
        $manifestPath = $archiveDir . DIRECTORY_SEPARATOR . $archiveBaseName . '.manifest.json';
        $workingManifestPath = $metaDir . DIRECTORY_SEPARATOR . 'manifest.json';
        $dbDumpPath = $dbDir . DIRECTORY_SEPARATOR . ((string) ($job['db_name'] ?? 'database') . '.sql');

        $this->ensureDirectory($jobRoot);
        $this->ensureDirectory($dbDir);
        $this->ensureDirectory($metaDir);

        $this->jobModel->update($jobId, [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
            'error_message' => null,
        ]);

        try {
            $manifest = [
                'generated_at' => date('c'),
                'job_public_id' => $publicId,
                'backup_type' => (string) $job['backup_type'],
                'environment_name' => (string) $job['environment_name'],
                'app_root' => $this->appRoot,
                'db_name' => (string) ($job['db_name'] ?? ''),
                'components' => [],
            ];

            if (in_array((string) $job['backup_type'], ['full', 'db_only'], true)) {
                $dbInfo = $this->createDatabaseDump($dbDumpPath);
                $manifest['components']['database'] = $dbInfo;
            }

            $applicationFiles = [];
            if (in_array((string) $job['backup_type'], ['full', 'code_only'], true)) {
                $applicationFiles = $this->collectApplicationFiles();
                $manifest['components']['application'] = [
                    'file_count' => count($applicationFiles),
                    'root' => $this->appRoot,
                ];
            }

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($workingManifestPath, $manifestJson);
            file_put_contents($manifestPath, $manifestJson);

            if (in_array((string) $job['backup_type'], ['full', 'code_only'], true)) {
                $this->createApplicationSnapshot(
                    $archivePath,
                    $workingManifestPath,
                    $applicationFiles,
                    (string) $job['backup_type'] === 'full',
                    (string) $job['backup_type'] === 'full' ? $dbDumpPath : null
                );
            } else {
                $this->createZipWithDatabaseOnly($archivePath, $dbDumpPath, $workingManifestPath);
            }

            $archiveSha256 = hash_file('sha256', $archivePath);
            $health = $this->performHealthChecks($archivePath, $manifestPath);

            $this->jobModel->update($jobId, [
                'status' => 'completed',
                'archive_path' => $archivePath,
                'archive_name' => $archiveName,
                'archive_size_bytes' => filesize($archivePath) ?: 0,
                'archive_sha256' => $archiveSha256,
                'manifest_path' => $manifestPath,
                'health_status' => $health['health_status'],
                'health_details_json' => json_encode($health, JSON_UNESCAPED_SLASHES),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            $this->deleteDirectory($jobRoot);
        } catch (\Throwable $e) {
            $this->jobModel->update($jobId, [
                'status' => 'failed',
                'health_status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
                'health_details_json' => json_encode([
                    'health_status' => 'failed',
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_SLASHES),
            ]);
            $this->deleteDirectory($jobRoot);
            throw $e;
        }

        return $this->jobModel->find($jobId) ?? [];
    }

    private function createDatabaseDump(string $dbDumpPath): array
    {
        $config = $this->getDefaultDbConfig();
        $mysqldump = $this->detectMysqlDump();
        $stderrPath = $dbDumpPath . '.stderr.log';

        $command = [
            $mysqldump,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--host=' . (string) ($config['hostname'] ?? '127.0.0.1'),
            '--port=' . (int) ($config['port'] ?? 3306),
            '--user=' . (string) ($config['username'] ?? 'root'),
            (string) ($config['database'] ?? ''),
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', $dbDumpPath, 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $env = null;
        if (($config['password'] ?? '') !== '') {
            $env = getenv();
            if (!is_array($env)) {
                $env = [];
            }
            $env['MYSQL_PWD'] = (string) $config['password'];
        }

        $process = proc_open($command, $descriptorSpec, $pipes, null, $env, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start mysqldump process.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0 || !is_file($dbDumpPath) || filesize($dbDumpPath) === 0) {
            $stderr = is_file($stderrPath) ? trim((string) file_get_contents($stderrPath)) : '';
            @unlink($stderrPath);
            throw new RuntimeException('Database dump failed' . ($stderr !== '' ? ': ' . $stderr : '.'));
        }

        @unlink($stderrPath);

        return [
            'path' => $dbDumpPath,
            'size_bytes' => filesize($dbDumpPath) ?: 0,
            'sha256' => hash_file('sha256', $dbDumpPath),
        ];
    }

    private function createApplicationSnapshot(string $archivePath, string $manifestPath, array $applicationFiles, bool $includeDatabase = false, ?string $dbDumpPath = null): void
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new RuntimeException('Unable to open backup zip archive for writing.');
        }

        if (!$zip->addFile($manifestPath, 'meta/manifest.json')) {
            $zip->close();
            throw new RuntimeException('Unable to add backup manifest to archive.');
        }

        if ($includeDatabase && $dbDumpPath) {
            if (!$zip->addFile($dbDumpPath, 'database/' . basename($dbDumpPath))) {
                $zip->close();
                throw new RuntimeException('Unable to add database dump to archive.');
            }
        }

        foreach ($applicationFiles as $absolutePath => $relativePath) {
            if (!$zip->addFile($absolutePath, 'application/' . str_replace('\\', '/', $relativePath))) {
                $zip->close();
                throw new RuntimeException('Unable to add application file to archive: ' . $relativePath);
            }
        }

        if ($zip->close() !== true) {
            throw new RuntimeException('Unable to finalize backup zip archive.');
        }
    }

    private function createZipWithDatabaseOnly(string $archivePath, string $dbDumpPath, string $manifestPath): void
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new RuntimeException('Unable to open backup zip archive for writing.');
        }

        if (!$zip->addFile($manifestPath, 'meta/manifest.json')) {
            $zip->close();
            throw new RuntimeException('Unable to add backup manifest to archive.');
        }
        if (!$zip->addFile($dbDumpPath, 'database/' . basename($dbDumpPath))) {
            $zip->close();
            throw new RuntimeException('Unable to add database dump to archive.');
        }
        if ($zip->close() !== true) {
            throw new RuntimeException('Unable to finalize backup zip archive.');
        }
    }

    private function performHealthChecks(string $archivePath, string $manifestPath): array
    {
        $result = [
            'health_status' => 'verified',
            'archive_exists' => is_file($archivePath),
            'archive_size_bytes' => is_file($archivePath) ? (filesize($archivePath) ?: 0) : 0,
            'manifest_exists' => is_file($manifestPath),
            'archive_sha256' => is_file($archivePath) ? hash_file('sha256', $archivePath) : null,
            'checks' => [],
        ];

        $manifestJson = is_file($manifestPath) ? (string) file_get_contents($manifestPath) : '';
        $zip = new ZipArchive();
        $openOk = $zip->open($archivePath) === true;
        $result['checks']['zip_open'] = $openOk;
        if ($openOk) {
            $result['checks']['entry_count'] = $zip->numFiles;
            $result['checks']['manifest_entry'] = $zip->locateName('meta/manifest.json') !== false;
            if ($manifestJson === '' && $result['checks']['manifest_entry']) {
                $manifestJson = (string) $zip->getFromName('meta/manifest.json');
            }
            $zip->close();
        } else {
            $result['checks']['entry_count'] = 0;
            $result['checks']['manifest_entry'] = false;
        }

        $decoded = $manifestJson !== '' ? json_decode($manifestJson, true) : null;
        $result['checks']['manifest_json'] = is_array($decoded) && !empty($decoded['generated_at']);

        foreach ($result['checks'] as $check) {
            if ($check === false) {
                $result['health_status'] = 'warning';
                break;
            }
        }

        if (!$result['archive_exists'] || !$result['checks']['zip_open']) {
            $result['health_status'] = 'failed';
        }

        return $result;
    }

    private function collectApplicationFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->appRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $fullPath = $fileInfo->getPathname();
            $relativePath = ltrim(substr($fullPath, strlen($this->appRoot)), '\\/');
            if ($relativePath === '') {
                continue;
            }

            if ($this->shouldExcludePath($relativePath)) {
                continue;
            }

            $files[$fullPath] = $relativePath;
        }

        return $files;
    }

    private function shouldExcludePath(string $relativePath): bool
    {
        $normalized = str_replace('/', '\\', $relativePath);
        $excludedPrefixes = [
            '.git\\',
            'writable\\',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (stripos($normalized, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function pruneScheduleBackups(int $scheduleId, int $retentionCount): void
    {
        $retentionCount = max(1, $retentionCount);
        $jobs = $this->jobModel
            ->where('schedule_id', $scheduleId)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'DESC')
            ->findAll();

        if (count($jobs) <= $retentionCount) {
            return;
        }

        $toDelete = array_slice($jobs, $retentionCount);
        foreach ($toDelete as $job) {
            if (!empty($job['archive_path']) && is_file((string) $job['archive_path'])) {
                @unlink((string) $job['archive_path']);
            }
            if (!empty($job['manifest_path']) && is_file((string) $job['manifest_path'])) {
                @unlink((string) $job['manifest_path']);
            }
            $this->jobModel->delete((int) $job['id']);
        }
    }

    private function extractDatabaseDumpFromArchive(string $archivePath, string $restoreRoot): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open backup archive for restore.');
        }

        $sqlPath = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = (string) $zip->getNameIndex($index);
            if (stripos($entry, 'database/') !== 0 || substr($entry, -4) !== '.sql') {
                continue;
            }

            $targetPath = $restoreRoot . DIRECTORY_SEPARATOR . basename($entry);
            $sourceStream = $zip->getStream($entry);
            if ($sourceStream === false) {
                $zip->close();
                throw new RuntimeException('Unable to read database dump from archive.');
            }

            $targetStream = fopen($targetPath, 'wb');
            if ($targetStream === false) {
                fclose($sourceStream);
                $zip->close();
                throw new RuntimeException('Unable to prepare extracted database dump file.');
            }

            stream_copy_to_stream($sourceStream, $targetStream);
            fclose($sourceStream);
            fclose($targetStream);
            $sqlPath = $targetPath;
            break;
        }

        $zip->close();
        return $sqlPath;
    }

    private function restoreDatabaseDump(string $sqlPath): void
    {
        if (!is_file($sqlPath)) {
            throw new RuntimeException('Restore SQL file is missing.');
        }

        $config = $this->getDefaultDbConfig();
        $mysql = $this->detectMysqlClient();
        $stderrPath = $sqlPath . '.restore.stderr.log';
        $command = [
            $mysql,
            '--host=' . (string) ($config['hostname'] ?? '127.0.0.1'),
            '--port=' . (int) ($config['port'] ?? 3306),
            '--user=' . (string) ($config['username'] ?? 'root'),
            (string) ($config['database'] ?? ''),
        ];
        $descriptorSpec = [
            0 => ['file', $sqlPath, 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $env = null;
        if (($config['password'] ?? '') !== '') {
            $env = getenv();
            if (!is_array($env)) {
                $env = [];
            }
            $env['MYSQL_PWD'] = (string) $config['password'];
        }

        $process = proc_open($command, $descriptorSpec, $pipes, null, $env, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start mysql restore process.');
        }

        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $stderr = is_file($stderrPath) ? trim((string) file_get_contents($stderrPath)) : '';
            @unlink($stderrPath);
            throw new RuntimeException('Database restore failed' . ($stderr !== '' ? ': ' . $stderr : '.'));
        }

        @unlink($stderrPath);
    }

    private function restoreApplicationFromArchive(string $archivePath): int
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open backup archive for application restore.');
        }

        $restoredFiles = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = (string) $zip->getNameIndex($index);
            if (stripos($entry, 'application/') !== 0 || str_ends_with($entry, '/')) {
                continue;
            }

            $relativePath = substr($entry, strlen('application/'));
            if ($relativePath === '' || $this->shouldExcludeRestorePath($relativePath)) {
                continue;
            }

            $targetPath = $this->appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $targetDir = dirname($targetPath);
            $this->ensureDirectory($targetDir);

            $sourceStream = $zip->getStream($entry);
            if ($sourceStream === false) {
                $zip->close();
                throw new RuntimeException('Unable to read application file from archive: ' . $relativePath);
            }

            $targetStream = fopen($targetPath, 'wb');
            if ($targetStream === false) {
                fclose($sourceStream);
                $zip->close();
                throw new RuntimeException('Unable to restore application file: ' . $relativePath);
            }

            stream_copy_to_stream($sourceStream, $targetStream);
            fclose($sourceStream);
            fclose($targetStream);
            $restoredFiles++;
        }

        $zip->close();
        return $restoredFiles;
    }

    private function shouldExcludeRestorePath(string $relativePath): bool
    {
        $normalized = str_replace('/', '\\', $relativePath);
        $excludedPrefixes = [
            'writable\\',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (stripos($normalized, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function getDefaultDbConfig(): array
    {
        $dbConfig = new \Config\Database();
        return is_array($dbConfig->default) ? $dbConfig->default : [];
    }

    private function detectMysqlDump(): string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('mysqldump executable was not found on this server.');
    }

    private function detectMysqlClient(): string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('mysql executable was not found on this server.');
    }

    private function normalizeBackupType(string $backupType): string
    {
        $backupType = strtolower(trim($backupType));
        if (!in_array($backupType, ['full', 'db_only', 'code_only'], true)) {
            throw new RuntimeException('Unsupported backup type.');
        }

        return $backupType;
    }

    private function ensureBackupDirectories(): void
    {
        $this->ensureDirectory($this->backupRoot);
        $this->ensureDirectory($this->backupRoot . DIRECTORY_SEPARATOR . 'archives');
        $this->ensureDirectory($this->backupRoot . DIRECTORY_SEPARATOR . 'working');
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}