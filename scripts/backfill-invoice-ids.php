<?php
declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable($base)->safeLoad();
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}

use GI\Core\DB;

$db = DB::getInstance();

$rows = $db->fetchAll("SELECT id, raw_response FROM payment_transactions WHERE invoice_id IS NULL AND raw_response IS NOT NULL");
if (!$rows) {
    echo "No candidate transactions found (invoice_id IS NULL).\n";
    exit(0);
}

$updated = 0;
$skipped = 0;
foreach ($rows as $r) {
    $id = $r['id'] ?? '';
    if ($id === '') {
        $skipped++;
        continue;
    }
    $raw = (string) ($r['raw_response'] ?? '');
    $decoded = json_decode($raw, true);
    $found = '';
    if (is_array($decoded)) {
        if (!empty($decoded['invoice_id'])) {
            $found = (string)$decoded['invoice_id'];
        } elseif (!empty($decoded['payload']['invoice_id'])) {
            $found = (string)$decoded['payload']['invoice_id'];
        }
    }
    if ($found === '') {
        $skipped++;
        continue;
    }
    // Verify invoice exists
    $inv = $db->fetch('SELECT id FROM billing_invoices WHERE id = :id', ['id' => $found]);
    if (!$inv || empty($inv['id'])) {
        $skipped++;
        continue;
    }
    try {
        $db->update('payment_transactions', ['invoice_id' => $found, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        $updated++;
        echo "Updated tx {$id} -> invoice {$found}\n";
    } catch (\Throwable $e) {
        echo "Failed to update tx {$id}: " . $e->getMessage() . "\n";
        $skipped++;
    }
}

echo "Done. Updated={$updated}, Skipped={$skipped}\n";
