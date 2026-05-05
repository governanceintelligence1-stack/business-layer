<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GI\Core\App;

$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

$app = new App();
$app->run();
