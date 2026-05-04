<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class DevTools extends BaseController
{
    /**
     * Show the Clear Data UI (dev-only)
     */
    public function index()
    {
        // Only available in development to avoid accidental production use
        if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Dev tools not available');
        }

        $this->requireAuth();

        // Define available modules and their description for the UI
        $modules = [
            'inventory' => 'Components, inventory transactions and adjustments',
            'sales' => 'Sales orders, quotations, customer invoices',
            'accounting' => 'Journal entries, ledgers, payments',
            'production' => 'Work orders, batches, production logs',
            'products' => 'Products, variants, product processes',
            'customers' => 'Customers and their addresses/contacts',
            'vendors' => 'Vendors and vendor contacts'
        ];

        return view('devtools/clear_data', $this->setPageData([
            'page_title' => 'Development Tools - Clear Data',
            'modules' => $modules
        ]));
    }

    /**
     * Clear data for a given module. POST only.
     */
    public function clear()
    {
        if ($this->request->getMethod() !== 'post') {
            return redirect()->to('/devtools/clear-data');
        }

        if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
            return redirect()->back()->with('error', 'Dev tools not available in this environment');
        }

        $this->requireAuth();

        $module = $this->request->getPost('module');
        $confirm = $this->request->getPost('confirm_token');

        // Simple safety: require a confirm token exactly 'CLEAR_NOW' to proceed
        if ($confirm !== 'CLEAR_NOW') {
            return redirect()->back()->with('error', 'Missing confirmation token. Type CLEAR_NOW in the confirmation box to proceed.');
        }

        // Define tables to clear per module (order matters for FK constraints)
        $map = [
            'inventory' => [
                'inventory_transactions',
                'components_inventory',
                'components',
            ],
            'sales' => [
                'customer_invoices',
                'sales_orders',
                'quotations',
                'sales_order_lines',
                'quotation_lines',
            ],
            'accounting' => [
                'journal_lines',
                'journals',
                'payments',
                'cheques',
            ],
            'production' => [
                'batches',
                'work_orders',
                'production_logs',
            ],
            'products' => [
                'product_variants',
                'product_processes',
                'products',
            ],
            'customers' => [
                'customer_addresses',
                'customer_contacts',
                'customers',
            ],
            'vendors' => [
                'vendor_contacts',
                'vendors',
            ]
        ];

        if (! isset($map[$module])) {
            return redirect()->back()->with('error', 'Unknown module');
        }

    $db = \Config\Database::connect();
        $tables = $map[$module];

        try {
            // Disable FK checks, truncate in order, then re-enable
            $db->query('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $t) {
                // Use TRUNCATE if table exists
                $res = $db->query("SHOW TABLES LIKE '" . $db->escapeString($t) . "'");
                if ($res && $res->getNumRows() > 0) {
                    $db->query("TRUNCATE TABLE `{$t}`");
                }
            }
            $db->query('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable $e) {
            // attempt to re-enable FK checks and return error
            try { $db->query('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $_) {}
            return redirect()->back()->with('error', 'Failed to clear data: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', ucfirst($module) . ' data cleared successfully.');
    }

    /**
     * Set the application environment by updating the root `env` file.
     * Only allowed in development or by authenticated users via confirmation.
     */
    public function setEnv()
    {
        if ($this->request->getMethod() !== 'post') {
            return redirect()->to('/devtools/clear-data');
        }

        $this->requireAuth();

        $target = $this->request->getPost('env');
        $confirm = $this->request->getPost('confirm_token');

        if ($confirm !== 'SET_ENV') {
            return redirect()->back()->with('error', 'Missing confirmation token. Type SET_ENV to proceed.');
        }

        // Only allow known environments
        $allowed = ['development', 'testing', 'production'];
        if (! in_array($target, $allowed)) {
            return redirect()->back()->with('error', 'Invalid environment specified.');
        }

        // Locate project root and env file
        $root = realpath(__DIR__ . '/../../..');
        $envFile = $root . DIRECTORY_SEPARATOR . 'env';

        if (! file_exists($envFile)) {
            // Try fallback to project root without realpath
            $envFile = getcwd() . DIRECTORY_SEPARATOR . 'env';
        }

        if (! is_writable($envFile)) {
            // attempt to make writable
            @chmod($envFile, 0666);
            if (! is_writable($envFile)) {
                return redirect()->back()->with('error', 'Cannot write to env file: ' . $envFile);
            }
        }

        $content = file_get_contents($envFile);
        if ($content === false) {
            return redirect()->back()->with('error', 'Failed to read env file');
        }

        if (preg_match('/^CI_ENVIRONMENT\\s*=.*/m', $content)) {
            $new = preg_replace('/^CI_ENVIRONMENT\\s*=.*/m', 'CI_ENVIRONMENT = ' . $target, $content);
        } else {
            $new = "CI_ENVIRONMENT = " . $target . "\n" . $content;
        }

        $written = file_put_contents($envFile, $new);
        if ($written === false) {
            return redirect()->back()->with('error', 'Failed to write env file');
        }

        // Attempt to update runtime server variable for immediate feedback (may not affect constant)
        $_SERVER['CI_ENVIRONMENT'] = $target;

        return redirect()->back()->with('success', 'Environment updated to ' . $target . '. Refresh the page to apply.');
    }
}
