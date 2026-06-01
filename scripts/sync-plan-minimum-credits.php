<?php
declare(strict_types=1);

/**
 * Ensures the starter plan grants at least enough monthly tokens (plans.credits_monthly) to use every active product once.
 *
 * Usage: php scripts/sync-plan-minimum-credits.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable($base)->safeLoad();

use GI\Core\DB;

try {
    $db = DB::getInstance();
    $pdo = $db->getPdo();

    $minimum = (float) $db->fetch(
        "SELECT COALESCE(SUM(credit_cost), 0) AS total FROM products WHERE status = 'active'"
    )['total'];

    echo "Minimum monthly tokens (sum of active product costs): " . number_format($minimum, 2) . "\n";

    if ($minimum <= 0) {
        echo "No active products with credit_cost — nothing to sync.\n";
        exit(0);
    }

    $starter = $db->fetch("SELECT id, slug, credits_monthly FROM plans WHERE slug = 'starter' LIMIT 1");
    if ($starter) {
        $current = (float) ($starter['credits_monthly'] ?? 0);
        if ($current < $minimum) {
            $db->getPdo()->prepare(
                'UPDATE plans SET credits_monthly = :credits WHERE id = :id'
            )->execute(['credits' => $minimum, 'id' => $starter['id']]);
            echo "Updated starter credits_monthly: {$current} → {$minimum}\n";
        } else {
            echo "Starter credits_monthly ({$current}) already meets minimum.\n";
        }
    } else {
        echo "No plan with slug 'starter' found — skipped starter update.\n";
    }

    echo "\nSuggested targets (set manually in DB if needed):\n";
    echo "  starter:       >= " . number_format($minimum, 0) . "\n";
    echo "  professional:  5000 (example)\n";
    echo "  enterprise:    20000 (example)\n";

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'sync-plan-minimum-credits failed: ' . $e->getMessage() . "\n");
    exit(1);
}
