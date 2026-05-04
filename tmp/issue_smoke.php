<?php
require 'vendor/autoload.php';
use Config\Services;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['REQUEST_URI'] = '/new-purchase-grns/9/lines/9/issue';
$_POST = [];
$payload = json_encode([
  'action_type' => 'scrap',
  'qty' => 0.1,
  'reason' => 'debug test',
  'action_date' => date('Y-m-d'),
]);
file_put_contents('php://temp', '');
// Cannot fully bootstrap request body in this quick script reliably; keep as smoke only
echo "smoke-only";
