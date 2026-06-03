<?php
declare(strict_types=1);

/**
 * Record demo token usage for dashboard / history UI testing.
 *
 * Usage:
 *   php scripts/mimic-token-usage.php
 *   php scripts/mimic-token-usage.php <organisation_id>
 *
 * Requires operations-api route POST /credits/debit-usage (restart operations-api after adding it).
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable($base)->safeLoad();

use GI\Core\ApiClient;
use GI\Services\TokenService;

$orgId = $argv[1] ?? ($_ENV['AUTH_BYPASS_ORGANISATION_ID'] ?? '');
if ($orgId === '') {
    fwrite(STDERR, "Usage: php scripts/mimic-token-usage.php [<organisation_id>]\n");
    exit(1);
}

$scenarios = [
    [
        'amount' => 480.0,
        'description' => 'OCR',
        'metadata' => ['dashboard_label' => 'OCR', 'product_slug' => 'ocr-document-analysis', 'pages' => 480],
    ],
    [
        'amount' => 1200.0,
        'description' => 'Upload Forensic Image',
        'metadata' => ['dashboard_label' => 'Upload Forensic Image', 'product_slug' => 'forensic-upload', 'gb' => 240],
    ],
    [
        'amount' => 750.0,
        'description' => 'Mobile Forensics',
        'metadata' => ['dashboard_label' => 'Mobile Forensics', 'product_slug' => 'mxa-mobile-analysis', 'cases' => 3],
    ],
];

$tokenService = new TokenService();
$opsUrl = (string) ($_ENV['OPERATIONS_API_URL'] ?? '');

echo "Organisation: {$orgId}\n";

$before = $tokenService->getAccountSnapshot($orgId);
echo sprintf(
    "Before — balance: %.2f, reserved: %.2f, available: %.2f\n",
    $before['balance'],
    $before['reserved'],
    $before['available']
);

$excessReserved = max(0.0, $before['reserved'] - 250.0);
if ($excessReserved > 0) {
    ApiClient::post($opsUrl, '/credits/release', [
        'organisation_id' => $orgId,
        'amount' => $excessReserved,
    ]);
    echo "Released {$excessReserved} tokens from orphaned demo reservations.\n";
}

foreach ($scenarios as $scenario) {
    $amount = (float) $scenario['amount'];
    $label = (string) $scenario['description'];
    echo "\n--- {$label} ({$amount} tokens) ---\n";

    if (!$tokenService->recordUsage(
        $orgId,
        $amount,
        $label,
        'usage',
        '',
        null,
        $scenario['metadata']
    )) {
        fwrite(STDERR, "  failed (is operations-api running with /credits/debit-usage?)\n");
        continue;
    }
    echo "  recorded\n";
}

$after = $tokenService->getAccountSnapshot($orgId);
echo sprintf(
    "\nAfter — balance: %.2f, reserved: %.2f, available: %.2f\n",
    $after['balance'],
    $after['reserved'],
    $after['available']
);

$tx = $tokenService->getRecentUsageTransactions($orgId, 5);
echo "\nLatest usage transactions (" . count($tx) . "):\n";
foreach ($tx as $row) {
    $name = (string) ($row['product_name'] ?? $row['description'] ?? '');
    $amt = (float) ($row['tokens_used'] ?? $row['amount'] ?? 0);
    $at = (string) ($row['created_at'] ?? '');
    echo "  [{$at}] {$name} — {$amt} tokens\n";
}

echo "\nRefresh /dashboard to see Recent Token Transactions.\n";
