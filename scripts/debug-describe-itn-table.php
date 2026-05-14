<?php
require __DIR__ . '/../vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__ . '/..');
use GI\Core\DB;
$db = DB::getInstance();
$cols = $db->fetchAll("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'payfast_itn_logs' ORDER BY ordinal_position");
print_r($cols);
