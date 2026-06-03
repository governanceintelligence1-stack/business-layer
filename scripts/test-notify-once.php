<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

$_ENV['PAYFAST_NOTIFY_SKIP_SIGNATURE'] = 'true';
putenv('PAYFAST_NOTIFY_SKIP_SIGNATURE=true');

$_POST = [
    'm_payment_id' => 'PF-5A5259950407A868',
    'pf_payment_id' => '3195638',
    'payment_status' => 'COMPLETE',
    'amount_gross' => '5000.00',
    'custom_str5' => '3e9bf676-a737-42e7-ab69-3dfb6fb00712',
];

ob_start();
(new \GI\Controllers\CheckoutController())->notify();
$out = ob_get_clean();
echo "notify output: {$out}\n";
echo "http_response_code: " . http_response_code() . "\n";
