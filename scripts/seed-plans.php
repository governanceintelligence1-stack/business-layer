<?php
declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable($base)->safeLoad();

use GI\Core\DB;

try {
    $db = DB::getInstance();
    $sql = file_get_contents($base . '/database/seeds/plans_seed.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read database/seeds/plans_seed.sql');
    }

    $db->getPdo()->exec($sql);
    echo "Plans seed applied successfully.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to seed plans: " . $e->getMessage() . "\n");
    exit(1);
}
