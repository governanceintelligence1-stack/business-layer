<?php
declare(strict_types=1);

use GI\Core\Router;
use GI\Controllers\HomeController;
use GI\Controllers\AuthController;
use GI\Controllers\DashboardController;
use GI\Controllers\OrganisationController;
use GI\Controllers\ProductController;
use GI\Controllers\PlanController;
use GI\Controllers\SubscriptionController;
use GI\Controllers\TokenController;
use GI\Controllers\ApiKeyController;
use GI\Controllers\BillingController;
use GI\Controllers\ApiController;
use GI\Controllers\UpdatesController;
use GI\Controllers\ProfileController;

/** @var Router $router */

// Public routes
$router->get('/', [HomeController::class, 'index']);

// Auth routes
$router->get('/auth/login', [AuthController::class, 'login']);
$router->get('/auth/callback', [AuthController::class, 'callback']);
$router->get('/auth/logout', [AuthController::class, 'logout']);
$router->get('/auth/register', [AuthController::class, 'register']);
$router->post('/auth/register', [AuthController::class, 'registerPost']);

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/updates', [UpdatesController::class, 'index']);
$router->get('/profile', [ProfileController::class, 'index']);

// Organisation
$router->get('/organisation', [OrganisationController::class, 'index']);
$router->post('/organisation', [OrganisationController::class, 'update']);
$router->get('/organisation/members', [OrganisationController::class, 'members']);

// Products
$router->get('/products', [ProductController::class, 'index']);
$router->get('/products/upload-forensic-image', [ProductController::class, 'uploadForensicImage']);
$router->get('/products/ocr', [ProductController::class, 'ocr']);
$router->get('/products/transcription', [ProductController::class, 'transcription']);
$router->get('/products/bank-statements', [ProductController::class, 'bankStatements']);
$router->get('/products/file-comparison', [ProductController::class, 'fileComparison']);

// Plans
$router->get('/plans', [PlanController::class, 'index']);

// Subscriptions & Checkout
$router->get('/subscriptions', [SubscriptionController::class, 'index']);
$router->post('/subscriptions/subscribe/{planId}', [SubscriptionController::class, 'subscribe']);
$router->post('/subscriptions/cancel', [SubscriptionController::class, 'cancel']);
$router->get('/subscriptions/history', [SubscriptionController::class, 'history']);
$router->get('/subscriptions/transactions', [SubscriptionController::class, 'transactions']);
$router->get('/checkout', [\GI\Controllers\CheckoutController::class, 'index']);
$router->post('/checkout/pay', [\GI\Controllers\CheckoutController::class, 'pay']);
$router->get('/checkout/return', [\GI\Controllers\CheckoutController::class, 'return']);
$router->get('/checkout/cancel', [\GI\Controllers\CheckoutController::class, 'cancel']);
$router->post('/checkout/notify', [\GI\Controllers\CheckoutController::class, 'notify']);

// Tokens (legacy /credits URLs redirect)
$router->get('/tokens', [TokenController::class, 'index']);
$router->get('/tokens/history', [TokenController::class, 'history']);
$router->get('/credits', [TokenController::class, 'redirectFromCredits']);
$router->get('/credits/history', [TokenController::class, 'redirectFromCreditsHistory']);

// API Keys
$router->get('/api-keys', [ApiKeyController::class, 'index']);
$router->post('/api-keys/create', [ApiKeyController::class, 'create']);
$router->post('/api-keys/revoke/{id}', [ApiKeyController::class, 'revoke']);

// Billing
$router->get('/billing', [BillingController::class, 'index']);
$router->post('/billing/payment-methods', [BillingController::class, 'storePaymentMethod']);
$router->get('/billing/invoice/{id}', [BillingController::class, 'invoice']);
$router->get('/billing/invoice/{id}/download', [BillingController::class, 'downloadInvoice']);
$router->get('/billing/history', [BillingController::class, 'history']);

// REST API v1
$router->get('/api/v1/health', [ApiController::class, 'health']);
$router->post('/api/v1/authorize', [ApiController::class, 'authorize']);
$router->post('/api/v1/reserve', [ApiController::class, 'reserve']);
$router->post('/api/v1/deduct', [ApiController::class, 'deduct']);
$router->post('/api/v1/capture', [ApiController::class, 'capture']);
$router->post('/api/v1/release', [ApiController::class, 'release']);
$router->get('/api/v1/balance/{org_id}', [ApiController::class, 'balance']);
$router->get('/api/v1/entitlement/{org_id}/{product_slug}', [ApiController::class, 'entitlement']);
$router->post('/api/v1/apikeys/validate', [ApiController::class, 'validateApiKey']);
$router->get('/api/v1/usage/{api_key}', [ApiController::class, 'usage']);
$router->get('/api/v1/products', [ApiController::class, 'products']);
$router->get('/api/v1/plans', [ApiController::class, 'plans']);
