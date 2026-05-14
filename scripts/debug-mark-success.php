<?php
require __DIR__ . '/../vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__ . '/..');

use GI\Services\PaymentTransactionService;
use GI\Core\DB;

$db = DB::getInstance();
$tx = $db->fetch('SELECT id FROM payment_transactions ORDER BY created_at DESC LIMIT 1');
if (!$tx) { echo "no tx\n"; exit(1); }
$id = $tx['id'];
$itn = ['pf_payment_id' => 'MANUAL-' . bin2hex(random_bytes(4)), 'payment_status' => 'COMPLETE'];
$svc = new PaymentTransactionService();
$r = $svc->markSuccessfulWithItn($id, $itn, null);
echo "markSuccessfulWithItn returned: " . intval($r) . "\n";
$after = $db->fetch('SELECT id,status,provider_transaction_id,raw_response FROM payment_transactions WHERE id = :id', ['id'=>$id]);
print_r($after);
