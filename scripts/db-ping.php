<?php
declare(strict_types=1);

/**
 * Quick PostgreSQL connectivity check (uses .env from project root).
 * Usage: php scripts/db-ping.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable($base)->safeLoad();

use GI\Core\DB;

try {
    $db = DB::getInstance();
    $row = $db->fetch(
        'SELECT current_database() AS db, current_user AS usr, version() AS ver'
    );
    echo "Connected.\n";
    echo '  database: ' . ($row['db'] ?? '') . "\n";
    echo '  user:     ' . ($row['usr'] ?? '') . "\n";
    echo '  server:   ' . substr((string)($row['ver'] ?? ''), 0, 80) . "…\n";
    $n = $db->fetch("SELECT count(*)::int AS c FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
    echo '  public tables: ' . ($n['c'] ?? '?') . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}
