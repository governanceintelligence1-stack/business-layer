<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\ApiResponse;
use GI\Core\Middleware;
use GI\Services\EntitlementService;
use GI\Services\TokenService;
use GI\Services\ApiKeyService;
use GI\Services\ProductService;
use GI\Services\PlanService;

class ApiController
{
    /** @param array<string, mixed> $body */
    private function estimatedTokensFromBody(array $body): float
    {
        if (isset($body['estimated_tokens'])) {
            return (float) $body['estimated_tokens'];
        }

        return (float) ($body['estimated_credits'] ?? 0);
    }

    public function health(): void
    {
        ApiResponse::success(['status' => 'ok', 'timestamp' => date('c')]);
    }

    public function authorize(): void
    {
        Middleware::apiAuth();
        $body   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $orgId  = $body['org_id'] ?? '';
        $slug   = $body['product_slug'] ?? '';
        $tokens = $this->estimatedTokensFromBody($body);

        if (empty($orgId) || empty($slug)) {
            ApiResponse::error('org_id and product_slug are required');
            return;
        }

        $entitlementService = new EntitlementService();
        $result             = $entitlementService->authorizeJob($orgId, $slug, $tokens);

        $summary = (new TokenService())->getAccountSummary($orgId);
        $result['balance']           = $summary['balance'];
        $result['reserved']          = $summary['reserved'];
        $result['available']         = $summary['available'];
        $result['tokens_available']  = $summary['available'];

        ApiResponse::success($result);
    }

    public function reserve(): void
    {
        Middleware::apiAuth();
        $body   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $orgId  = $body['org_id'] ?? '';
        $slug   = $body['product_slug'] ?? '';
        $tokens = $this->estimatedTokensFromBody($body);
        $jobId  = $body['job_id'] ?? '';

        if (empty($orgId) || empty($slug) || empty($jobId)) {
            ApiResponse::error('org_id, product_slug and job_id are required');
            return;
        }

        $entitlementService = new EntitlementService();
        $auth               = $entitlementService->authorizeJob($orgId, $slug, $tokens);

        if (!$auth['authorized']) {
            ApiResponse::error($auth['reason'], 402);
            return;
        }

        $reserveAmount = $tokens > 0 ? $tokens : (float) ($auth['token_cost'] ?? 0);
        $tokenService  = new TokenService();

        try {
            $tokenService->reserveTokens($orgId, $reserveAmount, $jobId);
        } catch (\RuntimeException $e) {
            ApiResponse::error($e->getMessage(), 402);
            return;
        }

        $summary = $tokenService->getAccountSummary($orgId);
        ApiResponse::success([
            'reserved'          => $reserveAmount,
            'job_id'            => $jobId,
            'reserved_tokens'   => $reserveAmount,
            'reserved_credits'  => $reserveAmount,
            'balance'           => $summary['balance'],
            'reserved_total'    => $summary['reserved'],
            'available'         => $summary['available'],
            'tokens_available'  => $summary['available'],
        ]);
    }

    public function deduct(): void
    {
        $this->capture();
    }

    public function capture(): void
    {
        Middleware::apiAuth();
        $body   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $orgId  = $body['org_id'] ?? '';
        $slug   = $body['product_slug'] ?? '';
        $amount = (float) ($body['amount'] ?? $body['tokens'] ?? 0);
        $jobId  = $body['job_id'] ?? '';

        if (empty($jobId) || $amount <= 0) {
            ApiResponse::error('job_id and amount are required. Reserve tokens before the job, then capture on success.');
            return;
        }

        $tokenService = new TokenService();
        $reservation  = $tokenService->findActiveReservationByJobId($jobId);
        if ($reservation === false) {
            ApiResponse::error('No active reservation for job_id. Call POST /api/v1/reserve first.', 409);
            return;
        }

        $reservationOrgId = (string) ($reservation['organisation_id'] ?? '');
        if ($orgId !== '' && $reservationOrgId !== $orgId) {
            ApiResponse::error('job_id does not belong to this organisation', 403);
            return;
        }

        $orgId = $reservationOrgId !== '' ? $reservationOrgId : $orgId;

        if ($slug !== '') {
            $entitlementService = new EntitlementService();
            $auth               = $entitlementService->authorizeJob($orgId, $slug, $amount);
            if (!$auth['authorized']) {
                ApiResponse::error($auth['reason'], 402);
                return;
            }
        }

        try {
            $tokenService->captureTokens($jobId, $amount);
        } catch (\RuntimeException $e) {
            ApiResponse::error($e->getMessage(), 402);
            return;
        }

        $summary = $tokenService->getAccountSummary($orgId);
        ApiResponse::success([
            'captured'         => $amount,
            'captured_tokens'  => $amount,
            'deducted'         => $amount,
            'deducted_tokens'  => $amount,
            'job_id'           => $jobId,
            'org_id'           => $orgId,
            'product_slug'     => $slug,
            'balance'          => $summary['balance'],
            'reserved_total'   => $summary['reserved'],
            'available'        => $summary['available'],
        ]);
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

        $tokenService = new TokenService();
        if (!$tokenService->releaseReservation($jobId)) {
            ApiResponse::error('No active reservation found for job_id', 404);
            return;
        }

        ApiResponse::success(['released' => true, 'job_id' => $jobId]);
    }

    public function balance(string $orgId): void
    {
        Middleware::apiAuth();

        if (empty($orgId)) {
            ApiResponse::error('org_id is required');
            return;
        }

        $tokenService = new TokenService();
        $summary      = $tokenService->getAccountSummary($orgId);
        $pending      = $tokenService->getActiveReservations($orgId, 50);
        ApiResponse::success([
            'org_id'               => $orgId,
            'balance'              => $summary['balance'],
            'reserved'             => $summary['reserved'],
            'available'            => $summary['available'],
            'token_balance'        => $summary['balance'],
            'tokens_reserved'      => $summary['reserved'],
            'tokens_available'     => $summary['available'],
            'pending_count'        => count($pending),
            'pending_reservations' => $pending,
        ]);
    }

    public function entitlement(string $orgId, string $productSlug): void
    {
        Middleware::apiAuth();

        $entitlementService = new EntitlementService();
        $evaluation         = $entitlementService->evaluateProductAccess($orgId, $productSlug);
        $tokenCost          = $evaluation['token_cost'];
        ApiResponse::success([
            'org_id'             => $orgId,
            'product_slug'       => $productSlug,
            'has_access'         => $evaluation['can_use'],
            'can_use'            => $evaluation['can_use'],
            'reason'             => $evaluation['reason'],
            'token_cost'         => $tokenCost,
            'credit_cost'        => $tokenCost,
            'available'          => $evaluation['available_balance'],
            'tokens_available'   => $evaluation['available_balance'],
            'balance'            => $evaluation['balance'],
            'token_balance'      => $evaluation['balance'],
            'reserved'           => $evaluation['reserved_balance'],
            'tokens_reserved'    => $evaluation['reserved_balance'],
            'has_subscription'   => $evaluation['has_subscription'],
        ]);
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
        $products       = $productService->getActive();
        foreach ($products as &$product) {
            $cost = (float) ($product['credit_cost'] ?? 0);
            $product['token_cost'] = $cost;
        }
        unset($product);
        ApiResponse::success($products);
    }

    public function plans(): void
    {
        Middleware::apiAuth();

        $planService = new PlanService();
        $plans       = $planService->getActive();
        foreach ($plans as &$plan) {
            $monthly = (float) ($plan['credits_monthly'] ?? 0);
            $plan['tokens_monthly'] = $monthly;
        }
        unset($plan);
        ApiResponse::success($plans);
    }
}
