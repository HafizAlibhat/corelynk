<?php

namespace App\Commands;

use App\Services\ControlPlaneInstaller;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class InstallControlPlane extends BaseCommand
{
    protected $group = 'SaaS';
    protected $name = 'company:install-control-plane';
    protected $description = 'Install/backfill only the multi-company control-plane migrations for snapshot-based deployments.';

    public function run(array $params)
    {
        try {
            $installer = new ControlPlaneInstaller();
            if (($params[0] ?? '') === 'down') {
                $installer->uninstallForDisposableTest();
                CLI::write('Disposable control-plane migration reversal completed.', 'green');
                return;
            }
            $installer->install();
            CLI::write('Multi-company control plane is installed.', 'green');
        } catch (\Throwable $e) {
            log_message('error', 'Control-plane installation failed: {message}', ['message' => $e->getMessage()]);
            CLI::error('Control-plane installation failed: ' . $e->getMessage());
        }
    }
}
