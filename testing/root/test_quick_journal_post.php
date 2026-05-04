<?php
// Simulate a POST to AccountingJournals::postQuick without running web server.
// Usage: php test_quick_journal_post.php

define('STDIN_TEST', true);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
  'entry_date' => date('Y-m-d'),
  'memo' => 'Controller Simulation Test',
  'account_debit' => 1, // adjust if account IDs differ
  'account_credit' => 2,
  'amount' => 77.25,
];

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();
// Minimal bootstrapping
require rtrim($paths->appDirectory, '/\\') . '/Common.php';
require rtrim($paths->systemDirectory, '/\\') . '/Common.php';
// Load CodeIgniter services
\Config\Services::autoloader();

// Instantiate controller manually
use App\Controllers\AccountingJournals;
use Config\Services;

$request = Services::request();
$response = Services::response();
$logger = Services::logger();

$controller = new AccountingJournals($request, $response, $logger);
$result = $controller->postQuick();

// If redirect response, output flash data and redirect target
if (method_exists($result, 'getHeaderLine')) {
    echo "Redirect: " . $result->getHeaderLine('Location') . "\n";
}

// Access session flashdata
$session = Services::session();
echo "Flash Success: " . ($session->getFlashdata('success') ?? 'NONE') . "\n";
echo "Flash Error: " . ($session->getFlashdata('error') ?? 'NONE') . "\n";

// Show latest entry and lines summary
$db = \Config\Database::connect();
$latest = $db->query('SELECT id, entry_date, memo, total_debits, total_credits FROM journal_entries ORDER BY id DESC LIMIT 1')->getRowArray();
echo "Latest Entry: " . json_encode($latest) . "\n";
$lines = [];
if ($latest) {
  $lines = $db->query('SELECT account_id,debit,credit FROM journal_lines WHERE entry_id=?', [$latest['id']])->getResultArray();
}
echo "Lines: " . json_encode($lines) . "\n";