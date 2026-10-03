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

/** Internal signal thrown when a running job's cancel_requested flag is seen — not a real error. */
class BackupCancelledException extends RuntimeException
{
}

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

    public function createManualBackup(string $backupType = 'full', ?int $initiatedBy = null, ?int $scheduleId = null, bool $excludeMedia = true): array
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

        return $this->processJob((int) $jobId, $excludeMedia);
    }

    /**
     * Creates the queued job row only — used when the actual archive work
     * will be run out-of-band (background CLI process) so the HTTP request
     * that triggered it can return immediately instead of blocking for the
     * duration of a large zip.
     */
    public function queueManualBackup(string $backupType = 'full', ?int $initiatedBy = null): array
    {
        $backupType = $this->normalizeBackupType($backupType);
        $publicId = $this->uuid4();
        $dbName = $this->getDefaultDbConfig()['database'] ?? null;

        $jobId = $this->jobModel->insert([
            'public_id' => $publicId,
            'job_type' => 'manual',
            'backup_type' => $backupType,
            'status' => 'queued',
            'environment_name' => 'production',
            'app_root' => $this->appRoot,
            'db_name' => $dbName,
            'initiated_by' => $initiatedBy,
            'health_status' => 'pending',
        ], true);

        if (!$jobId) {
            throw new RuntimeException('Unable to create backup job record.');
        }

        return $this->jobModel->find((int) $jobId) ?? [];
    }

    /** Runs the archive work for a job row that was previously queued via queueManualBackup(). */
    public function processQueuedJob(int $jobId, bool $excludeMedia = true): array
    {
        return $this->processJob($jobId, $excludeMedia);
    }

    /**
     * Flags a queued/running job for cancellation. The background worker
     * checks this flag between stages (and repeatedly while compressing)
     * and stops itself at the next safe checkpoint — no partial/corrupt
     * archive is ever left behind (see processJob()'s cleanup on cancel).
     */
    public function requestCancel(string $publicId): array
    {
        $job = $this->jobModel->where('public_id', $publicId)->first();
        if (!$job) {
            throw new RuntimeException('Backup job not found.');
        }

        if (!in_array((string) $job['status'], ['queued', 'running'], true)) {
            throw new RuntimeException('This backup already finished — nothing to cancel.');
        }

        $this->jobModel->update((int) $job['id'], [
            'cancel_requested' => 1,
            // A queued job has no worker running yet to pick this flag up — cancel it immediately.
            'status' => (string) $job['status'] === 'queued' ? 'cancelled' : 'running',
            'progress_stage' => (string) $job['status'] === 'queued' ? 'cancelled' : (string) ($job['progress_stage'] ?? ''),
            'completed_at' => (string) $job['status'] === 'queued' ? date('Y-m-d H:i:s') : null,
        ]);

        return $this->jobModel->find((int) $job['id']) ?? [];
    }

    private function assertNotCancelled(int $jobId): void
    {
        $job = $this->jobModel->select('cancel_requested')->find($jobId);
        if ($job && (int) ($job['cancel_requested'] ?? 0) === 1) {
            throw new BackupCancelledException('Backup cancelled by user.');
        }
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
        // Restore runs synchronously in the request that triggered it (unlike backup
        // creation, which is backgrounded) — without these, a slow dump/restore can hit
        // the web server's execution limit or die on client disconnect mid-restore,
        // leaving the database partially restored.
        @set_time_limit(0);
        ignore_user_abort(true);

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

        return [
            'backup_public_id' => $publicId,
            'safety_backup_public_id' => $safetyBackup['public_id'] ?? null,
        ] + $this->performDatabaseRestore((string) $job['archive_path'], $restoreMode);
    }

    /**
     * Restores directly from an uploaded backup archive with no matching
     * system_backup_jobs row and no prior data to safety-back-up — used by
     * the first-run setup wizard on a brand-new/empty database, where the
     * normal job-history-backed restoreBackup() flow has nothing to look up.
     */
    public function restoreFromUploadedArchive(string $archivePath, string $restoreMode = 'db_only'): array
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $restoreMode = strtolower(trim($restoreMode));
        if (!in_array($restoreMode, ['db_only', 'full'], true)) {
            throw new RuntimeException('Unsupported restore mode.');
        }
        if (!is_file($archivePath)) {
            throw new RuntimeException('Backup archive file is missing.');
        }

        $verification = $this->performHealthChecks($archivePath, '');
        if (($verification['health_status'] ?? 'failed') === 'failed') {
            throw new RuntimeException('The uploaded file is not a valid CoreLynk backup archive.');
        }

        return ['restore_mode' => $restoreMode] + $this->performDatabaseRestore($archivePath, $restoreMode);
    }

    private function performDatabaseRestore(string $archivePath, string $restoreMode): array
    {
        $restoreRoot = $this->backupRoot . DIRECTORY_SEPARATOR . 'working' . DIRECTORY_SEPARATOR . 'restore_' . $this->uuid4();
        $this->ensureDirectory($restoreRoot);

        try {
            $sqlPath = $this->extractDatabaseDumpFromArchive($archivePath, $restoreRoot);
            if ($sqlPath === null) {
                throw new RuntimeException('Database dump not found in backup archive.');
            }

            $this->restoreDatabaseDump($sqlPath);
            $summary = ['restore_mode' => $restoreMode, 'database_restored' => true];

            if ($restoreMode === 'full') {
                $summary['application_files_restored'] = $this->restoreApplicationFromArchive($archivePath);
            }

            return $summary;
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

    private function processJob(int $jobId, bool $excludeMedia = true): array
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
        // Written under a .building name and only renamed to the final name
        // once close()+verification succeed — a crash, cancel, or interrupted
        // process can never leave a corrupt/partial file sitting at a name
        // that looks like a finished backup.
        $buildingPath = $archivePath . '.building';
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
            'progress_percent' => 1,
            'progress_stage' => 'starting',
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
                $this->assertNotCancelled($jobId);
                $this->updateProgress($jobId, 5, 'dumping_database');
                $dbInfo = $this->createDatabaseDump($dbDumpPath);
                $manifest['components']['database'] = $dbInfo;
            }

            $applicationFiles = [];
            if (in_array((string) $job['backup_type'], ['full', 'code_only'], true)) {
                $this->assertNotCancelled($jobId);
                $this->updateProgress($jobId, 15, 'collecting_files');
                $applicationFiles = $this->collectApplicationFiles($excludeMedia);
                $manifest['components']['application'] = [
                    'file_count' => count($applicationFiles),
                    'root' => $this->appRoot,
                    'media_excluded' => $excludeMedia,
                ];
            }

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($workingManifestPath, $manifestJson);
            file_put_contents($manifestPath, $manifestJson);

            $this->assertNotCancelled($jobId);
            $this->updateProgress($jobId, 20, 'compressing');

            if (in_array((string) $job['backup_type'], ['full', 'code_only'], true)) {
                $this->createApplicationSnapshot(
                    $buildingPath,
                    $workingManifestPath,
                    $applicationFiles,
                    (string) $job['backup_type'] === 'full',
                    (string) $job['backup_type'] === 'full' ? $dbDumpPath : null,
                    $jobId
                );
            } else {
                $this->createZipWithDatabaseOnly($buildingPath, $dbDumpPath, $workingManifestPath, $jobId);
            }

            $this->assertNotCancelled($jobId);
            $this->updateProgress($jobId, 92, 'verifying');

            // Verify the archive *before* it ever gets the final name/DB
            // reference — CHECKCONS makes ZipArchive reject a corrupt/
            // truncated file outright instead of silently opening it.
            $health = $this->performHealthChecks($buildingPath, $manifestPath, true);
            if ($health['health_status'] === 'failed') {
                throw new RuntimeException('Backup archive failed integrity verification — discarded, nothing was saved.');
            }

            if (!rename($buildingPath, $archivePath)) {
                throw new RuntimeException('Unable to finalize backup archive on disk.');
            }

            $archiveSha256 = hash_file('sha256', $archivePath);

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
                'progress_percent' => 100,
                'progress_stage' => 'completed',
            ]);

            $this->deleteDirectory($jobRoot);
        } catch (BackupCancelledException $e) {
            @unlink($buildingPath);
            $this->jobModel->update($jobId, [
                'status' => 'cancelled',
                'health_status' => 'cancelled',
                'error_message' => 'Cancelled by user.',
                'completed_at' => date('Y-m-d H:i:s'),
                'progress_stage' => 'cancelled',
            ]);
            $this->deleteDirectory($jobRoot);
        } catch (\Throwable $e) {
            @unlink($buildingPath);
            $this->jobModel->update($jobId, [
                'status' => 'failed',
                'health_status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
                'progress_stage' => 'failed',
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
            // Under php-fpm, getenv() also returns non-string entries
            // (argv, argc, REQUEST_TIME...) that make proc_open() fail
            // with "Array to string conversion" when building the child
            // process environment.
            $env = array_filter(getenv() ?: [], static fn ($value) => is_string($value));
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

    private function createApplicationSnapshot(string $archivePath, string $manifestPath, array $applicationFiles, bool $includeDatabase = false, ?string $dbDumpPath = null, ?int $jobId = null): void
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

        $this->attachZipProgress($zip, $jobId);

        if ($zip->close() !== true) {
            throw new RuntimeException('Unable to finalize backup zip archive.');
        }
    }

    private function createZipWithDatabaseOnly(string $archivePath, string $dbDumpPath, string $manifestPath, ?int $jobId = null): void
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

        $this->attachZipProgress($zip, $jobId);

        if ($zip->close() !== true) {
            throw new RuntimeException('Unable to finalize backup zip archive.');
        }
    }

    /**
     * Maps ZipArchive::close()'s internal compression progress (0..1) onto the
     * job's overall 20-90% "compressing" band, since that's where nearly all
     * of the wall-clock time for a large backup is actually spent.
     * Throttled to avoid hammering the DB: at most ~4 writes/sec, and only on
     * a real percent change.
     */
    private function attachZipProgress(ZipArchive $zip, ?int $jobId): void
    {
        if ($jobId === null) {
            return;
        }

        $lastPercent = -1;
        $lastCheckAt = 0.0;

        $zip->registerProgressCallback(0.01, function (float $fraction) use ($jobId, &$lastPercent, &$lastCheckAt) {
            $now = microtime(true);
            // Cancellation must be checked on its own time-based clock, NOT
            // gated behind "percent changed" — one large file (e.g. a video)
            // can hold the percent steady for a long stretch of wall-clock
            // time while still actively compressing, which would otherwise
            // make a cancel request invisible for that whole stretch.
            if (($now - $lastCheckAt) < 0.25) {
                return;
            }
            $lastCheckAt = $now;
            $this->assertNotCancelled($jobId);

            $percent = 20 + (int) round($fraction * 70);
            if ($percent !== $lastPercent) {
                $lastPercent = $percent;
                $this->updateProgress($jobId, $percent, 'compressing');
            }
        });
    }

    private function updateProgress(int $jobId, int $percent, string $stage): void
    {
        try {
            $this->jobModel->update($jobId, [
                'progress_percent' => max(0, min(100, $percent)),
                'progress_stage' => $stage,
            ]);
        } catch (\Throwable $e) {
            // Progress reporting must never break the actual backup.
        }
    }

    /**
     * @param bool $strict When true, opens with CHECKCONS so a corrupt/
     * truncated zip (bad central directory, interrupted write, etc.) is
     * rejected outright instead of silently "succeeding". Used right after
     * building an archive, before it's ever renamed to its final name or
     * referenced from the DB, so a broken backup can never be mistaken for
     * a good one.
     */
    private function performHealthChecks(string $archivePath, string $manifestPath, bool $strict = false): array
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
        $openFlags = $strict ? ZipArchive::CHECKCONS : 0;
        $openOk = $zip->open($archivePath, $openFlags) === true;
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

    private function collectApplicationFiles(bool $excludeMedia = true): array
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

            if ($this->shouldExcludePath($relativePath, $excludeMedia)) {
                continue;
            }

            $files[$fullPath] = $relativePath;
        }

        return $files;
    }

    private function shouldExcludePath(string $relativePath, bool $excludeMedia = true): bool
    {
        $normalized = str_replace('/', '\\', $relativePath);
        $excludedPrefixes = [
            '.git\\',
            'writable\\',
            // Flutter/mobile build output — huge, regenerable, and its deep
            // nested paths (Android transform cache) can exceed Windows'
            // path-length limit or contain filenames ZipArchive rejects.
            'corelynk_mobile\\build\\',
        ];

        if ($excludeMedia) {
            // Product images/videos — large and reproducible from the live
            // server, so they're skipped by default to keep backups small.
            $excludedPrefixes[] = 'public\\uploads\\';
        }

        foreach ($excludedPrefixes as $prefix) {
            if (stripos($normalized, $prefix) === 0) {
                return true;
            }
        }

        // node_modules / build / .dart_tool anywhere in the tree — same
        // reasoning as above, wherever a nested project keeps them.
        if (preg_match('/\\\\(node_modules|build|\.dart_tool)\\\\/i', $normalized)) {
            return true;
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

    /**
     * Builds a brand-new database from the bundled schema snapshot
     * (app/Database/schema.sql) instead of replaying migration history,
     * which is unreliable from zero on this project (some tables were
     * hand-added outside migrations, breaking migration ordering).
     */
    public function installFreshSchema(): void
    {
        $schemaPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'schema.sql';
        if (!is_file($schemaPath)) {
            throw new RuntimeException('Schema snapshot (app/Database/schema.sql) is missing.');
        }

        $this->restoreDatabaseDump($schemaPath);
    }

    /** Install the canonical snapshot into an explicitly provisioned database. */
    public function installFreshSchemaForDatabase(array $databaseConfig): void
    {
        $schemaPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'schema.sql';
        if (! is_file($schemaPath)) {
            throw new RuntimeException('Schema snapshot (app/Database/schema.sql) is missing.');
        }
        $this->restoreDatabaseDump($schemaPath, $databaseConfig);
    }

    private function restoreDatabaseDump(string $sqlPath, ?array $databaseConfig = null): void
    {
        if (!is_file($sqlPath)) {
            throw new RuntimeException('Restore SQL file is missing.');
        }

        $config = $databaseConfig ?? $this->getDefaultDbConfig();
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
            // Under php-fpm, getenv() also returns non-string entries
            // (argv, argc, REQUEST_TIME...) that make proc_open() fail
            // with "Array to string conversion" when building the child
            // process environment.
            $env = array_filter(getenv() ?: [], static fn ($value) => is_string($value));
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

    /**
     * Locate the actual php-cli executable. Critical: under Apache mod_php
     * (SAPI apache2handler, what this server runs), the PHP_BINARY constant
     * resolves to httpd.exe, not php.exe — using it to spawn a background
     * "php spark ..." process silently launches Apache with garbage
     * arguments instead of running the backup, leaving the job stuck at
     * "queued" forever with no error anywhere. Never use PHP_BINARY here.
     */
    public function detectPhpCliBinary(): string
    {
        return $this->detectXamppBinary('php.exe', 'php', 'php');
    }

    private function detectMysqlDump(): string
    {
        return $this->detectXamppBinary('mysqldump.exe', 'mysqldump', 'mysql' . DIRECTORY_SEPARATOR . 'bin');
    }

    private function detectMysqlClient(): string
    {
        return $this->detectXamppBinary('mysql.exe', 'mysql', 'mysql' . DIRECTORY_SEPARATOR . 'bin');
    }

    /**
     * Locate a binary that ships with this app's own XAMPP install. Checks
     * the XAMPP install this app actually lives under (works regardless of
     * drive letter, e.g. D:\xampp vs C:\xampp), then common fixed install
     * paths, then PATH.
     */
    private function detectXamppBinary(string $winName, string $unixName, string $xamppSubPath): string
    {
        $candidates = [];

        $xamppBin = $this->guessXamppBinDir($xamppSubPath);
        if ($xamppBin !== null) {
            $candidates[] = $xamppBin . DIRECTORY_SEPARATOR . $winName;
        }

        $candidates[] = 'C:\\xampp\\' . $xamppSubPath . '\\' . $winName;
        if ($xamppSubPath === 'mysql' . DIRECTORY_SEPARATOR . 'bin') {
            $candidates[] = 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\' . $winName;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $onPath = $this->findBinaryOnPath($winName, $unixName);
        if ($onPath !== null) {
            return $onPath;
        }

        throw new RuntimeException(ucfirst($unixName) . ' executable was not found on this server (checked ' . implode(', ', $candidates) . ' and PATH).');
    }

    /**
     * Derive <xampp_root>\<subPath> from this app's root (…\xampp\htdocs\corelynk).
     */
    private function guessXamppBinDir(string $subPath): ?string
    {
        $xamppRoot = dirname($this->appRoot, 2);
        $bin = $xamppRoot . DIRECTORY_SEPARATOR . $subPath;

        return is_dir($bin) ? $bin : null;
    }

    private function findBinaryOnPath(string $winName, string $unixName): ?string
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $command = $isWindows ? 'where ' . escapeshellarg($winName) : 'which ' . escapeshellarg($unixName);
        $output = @shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        $first = trim(strtok($output, "\r\n"));

        return ($first !== '' && is_file($first)) ? $first : null;
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
