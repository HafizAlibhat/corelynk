<?php

namespace App\Commands;

use App\Services\SystemBackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SystemBackup extends BaseCommand
{
    protected $group = 'System';
    protected $name = 'system:backup';
    protected $description = 'Create manual backups or run due scheduled backups.';
    protected $usage = 'system:backup [--type=full] [--run-schedules] [--verify=<public_id>] [--job-id=<id>] [--include-media]';
    protected $options = [
        '--type' => 'Backup type: full, db_only, code_only',
        '--run-schedules' => 'Execute all due backup schedules now.',
        '--verify' => 'Re-run health verification for a backup public id.',
        '--job-id' => 'Process an already-queued job row instead of creating a new one (used for background/async runs).',
        '--include-media' => 'Include public/uploads in the archive (excluded by default).',
    ];

    public function run(array $params)
    {
        $service = new SystemBackupService();
        $args = $this->getRawArgs();

        if ($this->hasOption($args, '--run-schedules')) {
            $count = $service->processDueSchedules();
            CLI::write('Processed scheduled backups: ' . $count, 'green');
            return;
        }

        $verifyId = $this->getOptionValue($args, '--verify');
        if ($verifyId !== null && $verifyId !== '') {
            $job = $service->verifyBackup($verifyId);
            CLI::write('Verified backup: ' . ($job['public_id'] ?? $verifyId), 'green');
            CLI::write('Health: ' . ($job['health_status'] ?? 'unknown'));
            return;
        }

        $excludeMedia = !$this->hasOption($args, '--include-media');

        $jobId = $this->getOptionValue($args, '--job-id');
        if ($jobId !== null && $jobId !== '') {
            $job = $service->processQueuedJob((int) $jobId, $excludeMedia);
            CLI::write('Backup created: ' . ($job['archive_name'] ?? 'unknown'), 'green');
            CLI::write('Public ID: ' . ($job['public_id'] ?? 'unknown'));
            CLI::write('Health: ' . ($job['health_status'] ?? 'unknown'));
            return;
        }

        $type = $this->getOptionValue($args, '--type') ?? 'full';
        $job = $service->createManualBackup($type, null, null, $excludeMedia);
        CLI::write('Backup created: ' . ($job['archive_name'] ?? 'unknown'), 'green');
        CLI::write('Public ID: ' . ($job['public_id'] ?? 'unknown'));
        CLI::write('Health: ' . ($job['health_status'] ?? 'unknown'));
    }

    private function getRawArgs(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        if (!is_array($argv)) {
            return [];
        }

        return array_values(array_slice($argv, 2));
    }

    private function hasOption(array $params, string $name): bool
    {
        foreach ($params as $param) {
            if ($param === $name || str_starts_with($param, $name . '=')) {
                return true;
            }
        }

        return false;
    }

    private function getOptionValue(array $params, string $name): ?string
    {
        foreach ($params as $index => $param) {
            if (str_starts_with($param, $name . '=')) {
                return (string) substr($param, strlen($name) + 1);
            }
            if ($param === $name) {
                $next = $params[$index + 1] ?? null;
                if (is_string($next) && !str_starts_with($next, '--')) {
                    return $next;
                }
            }
        }

        return null;
    }
}