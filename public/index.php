<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// PayFast ITN: capture raw POST before the stream is consumed (needed for MD5 over exact field order).
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
$path = '/' . trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), '/');
$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if ($method === 'POST' && ($path === '/checkout/notify' || $script === 'payfast-notify.php')) {
    $GLOBALS['GI_PAYFAST_RAW_POST'] = (string) file_get_contents('php://input');
}

require BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GI\Core\App;

$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

$app = new App();
$app->run();
