<?php

namespace App\Commands;

use App\Services\DataPortabilityService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DataPortability extends BaseCommand
{
    protected $group = 'System';
    protected $name = 'data:portability';
    protected $description = 'Process a queued data export/import job (used for background/async runs).';
    protected $usage = 'data:portability --job-id=<id>';
    protected $options = [
        '--job-id' => 'Process an already-queued export or import job row.',
    ];

    public function run(array $params)
    {
        $args = $this->getRawArgs();
        $jobId = $this->getOptionValue($args, '--job-id');

        if ($jobId === null || $jobId === '') {
            CLI::error('Missing required --job-id.');
            return;
        }

        $service = new DataPortabilityService();
        $job = $service->processQueuedJob((int) $jobId);

        CLI::write('Job ' . ($job['direction'] ?? '?') . ' finished with status: ' . ($job['status'] ?? 'unknown'), 'green');
        CLI::write('Public ID: ' . ($job['public_id'] ?? 'unknown'));
    }

    private function getRawArgs(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        if (!is_array($argv)) {
            return [];
        }

        return array_values(array_slice($argv, 2));
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
