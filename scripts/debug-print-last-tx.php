<?php
require __DIR__ . '/../vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__ . '/..');
use GI\Core\DB;
$db = DB::getInstance();
$t = $db->fetch('SELECT id, status, raw_response FROM payment_transactions ORDER BY created_at DESC LIMIT 1');
print_r($t);
