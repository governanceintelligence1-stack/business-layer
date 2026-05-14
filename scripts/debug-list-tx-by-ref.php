<?php
if ($argc < 2) {
    echo "Usage: php debug-list-tx-by-ref.php <merchant_reference>\n";
    exit(1);
}
$ref = $argv[1];
require __DIR__ . '/../vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__ . '/..');
use GI\Core\DB;
$db = DB::getInstance();
$rows = $db->fetchAll('SELECT id, merchant_reference, status, provider_transaction_id, raw_response, created_at FROM payment_transactions WHERE merchant_reference = :r ORDER BY created_at ASC', ['r' => $ref]);
print_r($rows);
