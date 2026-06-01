<?php
declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable($base)->safeLoad();

use GI\Core\DB;

$db = DB::getInstance();
$users = $db->fetchAll(
    'SELECT u.email, u.role, o.name AS organisation
     FROM users u
     LEFT JOIN organisations o ON o.id = u.organisation_id
     ORDER BY u.created_at ASC
     LIMIT 10'
);

echo "Users in gi_business_layer:\n";
if ($users === []) {
    echo "  (none — bypass will use dev-user fallback)\n";
    exit(0);
}
foreach ($users as $u) {
    echo '  - ' . ($u['email'] ?? '') . ' [' . ($u['role'] ?? '') . '] @ ' . ($u['organisation'] ?? '—') . "\n";
}
