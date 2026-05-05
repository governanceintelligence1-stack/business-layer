<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\ApiResponse;
use GI\Core\Middleware;
use GI\Services\EntitlementService;
use GI\Services\CreditService;
use GI\Services\ApiKeyService;
use GI\Services\ProductService;
use GI\Services\PlanService;

class ApiController
{
    public function health(): void
    {
        ApiResponse::success(['status' => 'ok', 'timestamp' => date('c')]);
    }

    public function authorize(): void
    {
        Middleware::apiAuth();
        $body    = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $orgId   = $body['org_id'] ?? '';
        $slug    = $body['product_slug'] ?? '';
        $credits = (float) ($body['estimated_credits'] ?? 0);

        if (empty($orgId) || empty($slug)) {
            ApiResponse::error('org_id and product_slug are required');
            return;
        }

        $entitlementService = new EntitlementService();
        $result             = $entitlementService->authorizeJob($orgId, $slug, $credits);
        ApiResponse::success($result);
    }

    public function reserve(): void
    {
        Middleware::apiAuth();
        $body    = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $orgId   = $body['org_id'] ?? '';
        $slug    = $body['product_slug'] ?? '';
        $credits = (float) ($body['estimated_credits'] ?? 0);
        $jobId   = $body['job_id'] ?? '';

        if (empty($orgId) || empty($slug) || empty($jobId)) {
            ApiResponse::error('org_id, product_slug and job_id are required');
            return;
        }

        $entitlementService = new EntitlementService();
        $auth               = $entitlementService->authorizeJob($orgId, $slug, $credits);

        if (!$auth['authorized']) {
            ApiResponse::error($auth['reason'], 402);
            return;
        }

        $creditService = new CreditService();
        $creditService->reserveCredits($orgId, $credits, $jobId);
        ApiResponse::success(['reserved' => $credits, 'job_id' => $jobId]);
    }

    public function deduct(): void
    {
        Middleware::apiAuth();
        $body   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $orgId  = $body['org_id'] ?? '';
        $amount = (float) ($body['amount'] ?? 0);
        $jobId  = $body['job_id'] ?? '';
        $desc   = $body['description'] ?? 'API deduction';

        if (empty($orgId) || $amount <= 0) {
            ApiResponse::error('org_id and amount are required');
            return;
        }

        $creditService = new CreditService();
        $creditService->deductCredits($orgId, $amount, $desc, 'job', $jobId);
        ApiResponse::success(['deducted' => $amount, 'org_id' => $orgId]);
    }

    public function release(): void
    {
        Middleware::apiAuth();
        $body  = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $jobId = $body['job_id'] ?? '';

        if (empty($jobId)) {
            ApiResponse::error('job_id is required');
            return;
        }

        $creditService = new CreditService();
        $creditService->releaseReservation($jobId);
        ApiResponse::success(['released' => true, 'job_id' => $jobId]);
    }

    public function balance(string $orgId): void
    {
        Middleware::apiAuth();

        if (empty($orgId)) {
            ApiResponse::error('org_id is required');
            return;
        }

        $creditService = new CreditService();
        $balance       = $creditService->getBalance($orgId);
        $available     = $creditService->getAvailableBalance($orgId);
        ApiResponse::success(['org_id' => $orgId, 'balance' => $balance, 'available' => $available]);
    }

    public function entitlement(string $orgId, string $productSlug): void
    {
        Middleware::apiAuth();

        $entitlementService = new EntitlementService();
        $hasAccess          = $entitlementService->checkAccess($orgId, $productSlug);
        ApiResponse::success(['org_id' => $orgId, 'product_slug' => $productSlug, 'has_access' => $hasAccess]);
    }

    public function validateApiKey(): void
    {
        $body        = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $apiKey      = $body['api_key'] ?? '';
        $productSlug = $body['product_slug'] ?? '';

        if (empty($apiKey) || empty($productSlug)) {
            ApiResponse::error('api_key and product_slug are required');
            return;
        }

        $entitlementService = new EntitlementService();
        $valid              = $entitlementService->checkApiKey($apiKey, $productSlug);
        ApiResponse::success(['valid' => $valid]);
    }

    public function usage(string $apiKey): void
    {
        Middleware::apiAuth();

        $apiKeyService = new ApiKeyService();
        $stats         = $apiKeyService->getUsageStats($apiKey);
        ApiResponse::success(['api_key' => $apiKey, 'usage' => $stats]);
    }

    public function products(): void
    {
        Middleware::apiAuth();

        $productService = new ProductService();
        ApiResponse::success($productService->getActive());
    }

    public function plans(): void
    {
        Middleware::apiAuth();

        $planService = new PlanService();
        ApiResponse::success($planService->getActive());
    }
}
