<?php
declare(strict_types=1);

/**
 * Creates a pending PayFast-style payment row, then runs the same activation
 * path as POST /checkout/notify (COMPLETE) so you can verify DB rows without PayFast.
 *
 * Usage: php scripts/simulate-checkout-itn.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable($base)->safeLoad();

if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}

// Synthetic ITN only: this process must accept notify without a PayFast signature.
$_ENV['PAYFAST_NOTIFY_SKIP_SIGNATURE'] = 'true';
putenv('PAYFAST_NOTIFY_SKIP_SIGNATURE=true');

use GI\Controllers\CheckoutController;
use GI\Core\DB;
use GI\Services\PaymentTransactionService;

$db = DB::getInstance();
$org = $db->fetch('SELECT id, name FROM organisations ORDER BY created_at ASC LIMIT 1');
if (!$org || empty($org['id'])) {
    fwrite(STDERR, "No organisation found.\n");
    exit(1);
}
$orgId = (string) $org['id'];

$plan = $db->fetch("SELECT id, name, slug, price_monthly FROM plans WHERE status = 'active' ORDER BY price_monthly ASC LIMIT 1");
if (!$plan || empty($plan['id'])) {
    fwrite(STDERR, "No active plan found.\n");
    exit(1);
}
$planId = (string) $plan['id'];
$amount = (float) ($plan['price_monthly'] ?? 0);

$user = $db->fetch('SELECT id FROM users WHERE organisation_id = :o ORDER BY created_at ASC LIMIT 1', ['o' => $orgId]);
$userId = $user && !empty($user['id']) ? (string) $user['id'] : '';

$ref = 'PF-SIM-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

$txSvc = new PaymentTransactionService();
$txSvc->createPending(
    $orgId,
    $userId,
    $planId,
    null,
    $ref,
    $amount,
    [
        'billing_cycle' => 'monthly',
        'plan_name' => (string) ($plan['name'] ?? 'Plan'),
        'requested_plan_id' => $planId,
        'is_mock_plan' => false,
    ]
);

echo "Created pending payment merchant_reference={$ref} amount={$amount} plan={$plan['slug']}\n";

$_POST = [
    'm_payment_id' => $ref,
    'payment_status' => 'COMPLETE',
    'pf_payment_id' => 'SIM-' . bin2hex(random_bytes(6)),
    'amount_gross' => number_format($amount, 2, '.', ''),
    'merchant_id' => $_ENV['PAYFAST_MERCHANT_ID'] ?? '',
];

ob_start();
(new CheckoutController())->notify();
$out = ob_get_clean();
echo "notify response: {$out}\n";

$tx = $db->fetch(
    'SELECT id, status, amount, invoice_id, provider_transaction_id FROM payment_transactions WHERE merchant_reference = :r',
    ['r' => $ref]
);
echo "\n-- payment_transactions --\n";
print_r($tx);

$sub = $db->fetch(
    "SELECT s.id, s.status, s.plan_id, p.name AS plan_name, s.renewal_amount
     FROM subscriptions s JOIN plans p ON p.id = s.plan_id
     WHERE s.organisation_id = :o ORDER BY s.created_at DESC LIMIT 1",
    ['o' => $orgId]
);
echo "\n-- latest subscription (org) --\n";
print_r($sub);

$inv = $db->fetch(
    'SELECT id, invoice_number, status, total, amount_paid FROM billing_invoices WHERE organisation_id = :o ORDER BY created_at DESC LIMIT 1',
    ['o' => $orgId]
);
echo "\n-- latest invoice (org) --\n";
print_r($inv);

$ct = $db->fetch(
    "SELECT type, amount, balance_after FROM credit_transactions WHERE organisation_id = :o ORDER BY created_at DESC LIMIT 3",
    ['o' => $orgId]
);
echo "\n-- latest credit_transactions (up to 3) --\n";
print_r($ct);
