<?php
declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable($base)->safeLoad();
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}

$_ENV['PAYFAST_NOTIFY_SKIP_SIGNATURE'] = 'true';
putenv('PAYFAST_NOTIFY_SKIP_SIGNATURE=true');

use GI\Controllers\CheckoutController;
use GI\Core\DB;

$ref = $argv[1] ?? 'PF-20260601091929-9CAF97E0';
$raw = $argv[2] ?? 'm_payment_id=PF-20260601091929-9CAF97E0&pf_payment_id=3190371&payment_status=COMPLETE&item_name=Starter&amount_gross=5000.00&custom_str1=16145349-6f70-4b5e-9873-70107d2cecb8&custom_str2=07b2409f-771f-4fca-a64c-077e6f244072&custom_str3=annual&custom_str4=faf195e4-1998-46ec-a974-d18ccb87faff&custom_str5=f74df54f-7471-4068-a088-f681aec464f3&merchant_id=10048671';

$db = DB::getInstance();
$tx = $db->fetch(
    'SELECT pt.id, pt.status, pt.amount, bi.total, bi.credits_granted
     FROM payment_transactions pt
     LEFT JOIN billing_invoices bi ON bi.id = pt.invoice_id
     WHERE pt.merchant_reference = :r',
    ['r' => $ref]
);
echo "Before ITN:\n";
print_r($tx);

$GLOBALS['GI_PAYFAST_RAW_POST'] = $raw;

try {
    ob_start();
    (new CheckoutController())->notify();
    $out = ob_get_clean();
    echo "\nNotify output: {$out}\n";
    echo "HTTP response code was set in notify()\n";
} catch (Throwable $e) {
    echo "\nEXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

$tx2 = $db->fetch(
    'SELECT pt.id, pt.status, bi.credits_granted FROM payment_transactions pt
     LEFT JOIN billing_invoices bi ON bi.id = pt.invoice_id
     WHERE pt.merchant_reference = :r',
    ['r' => $ref]
);
echo "\nAfter ITN:\n";
print_r($tx2);
