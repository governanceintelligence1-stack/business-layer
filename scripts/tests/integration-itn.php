<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable($base)->safeLoad();
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}

use GI\Core\DB;

// Run the simulate script as a subprocess to avoid side-effects in this process.
$cmd = PHP_BINARY . ' ' . escapeshellarg($base . '/scripts/simulate-checkout-itn.php');
passthru($cmd, $exitCode);
if ($exitCode !== 0) {
    echo "simulate-checkout-itn.php failed with exit code {$exitCode}\n";
    exit(2);
}

// After simulation, assert the last created payment transaction is successful.
$db = DB::getInstance();
$tx = null;
$tries = 0;
$max = 10; // total wait ~5s
while ($tries++ < $max) {
    $tx = $db->fetch('SELECT id, status, invoice_id, provider_transaction_id FROM payment_transactions ORDER BY created_at DESC LIMIT 1');
    if ($tx && !empty($tx['id']) && ($tx['status'] ?? '') === 'successful') {
        break;
    }
    usleep(500000);
}

if (!$tx || empty($tx['id'])) {
    echo "No payment transactions found after simulation.\n";
    exit(3);
}

if (($tx['status'] ?? '') !== 'successful') {
    echo "Latest transaction not successful: " . json_encode($tx) . "\n";
    exit(4);
}

if (empty($tx['invoice_id'])) {
    echo "Latest transaction missing invoice_id: " . json_encode($tx) . "\n";
    exit(5);
}

echo "Integration ITN test passed. tx_id=" . $tx['id'] . "\n";
exit(0);
