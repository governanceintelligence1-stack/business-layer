<?php

declare(strict_types=1);

/**
 * Standalone PayFast ITN entry point (same handler as POST /checkout/notify).
 * Point PayFast notify_url here if your deployment prefers a dedicated script path.
 */

$GLOBALS['GI_PAYFAST_RAW_POST'] = (string) file_get_contents('php://input');

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();

(new \GI\Controllers\CheckoutController())->notify();
