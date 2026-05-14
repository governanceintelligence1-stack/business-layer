<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

use GI\Core\DB;

$db = DB::getInstance();
$rows = $db->fetchAll(
    'SELECT id, organisation_id, invoice_id, provider, provider_transaction_id, merchant_reference, amount, currency, status, raw_response, created_at, updated_at FROM payment_transactions ORDER BY created_at DESC LIMIT 20'
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
