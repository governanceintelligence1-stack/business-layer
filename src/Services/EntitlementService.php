<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class EntitlementService
{
    private string $operationsApiUrl;
    private SubscriptionService $subscriptionService;
    private TokenService $tokenService;
    private ApiKeyService $apiKeyService;
    private ProductService $productService;

    public function __construct()
    {
        $this->operationsApiUrl    = (string) ($_ENV['OPERATIONS_API_URL'] ?? '');
        $this->subscriptionService = new SubscriptionService();
        $this->tokenService        = new TokenService();
        $this->apiKeyService       = new ApiKeyService();
        $this->productService      = new ProductService();
    }

    /** @return array<string, mixed>|list<mixed> */
    private function unwrap(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    public function getMinimumMonthlyTokens(): float
    {
        $resp = ApiClient::get($this->operationsApiUrl, '/plans/minimum-monthly-credits');
        $data = $this->unwrap($resp);

        return (float) ($data['minimum_monthly_credits'] ?? $data['total'] ?? 0);
    }

    public function hasActiveSubscription(string $orgId): bool
    {
        return $this->subscriptionService->getActive($orgId) !== false;
    }

    public function getProductTokenCost(string $productSlug): ?float
    {
        $product = $this->productService->findBySlug($productSlug);
        if (!$product) {
            return null;
        }

        return (float) ($product['credit_cost'] ?? $product['token_cost'] ?? 0);
    }

    public function getProductCreditCost(string $productSlug): ?float
    {
        return $this->getProductTokenCost($productSlug);
    }

    public function checkAccess(string $orgId, string $productSlug): bool
    {
        return $this->evaluateProductAccess($orgId, $productSlug)['can_use'];
    }

    public function evaluateProductAccess(string $orgId, string $productSlug): array
    {
        $resp = ApiClient::get(
            $this->operationsApiUrl,
            '/entitlements/' . urlencode($orgId) . '/' . urlencode($productSlug)
        );
        $httpCode = (int) ($resp['_http_code'] ?? 0);
        if ($httpCode >= 400 || isset($resp['error'])) {
            $resp = [];
        }

        $remote = $this->unwrap($resp);
        if (is_array($remote) && $remote !== [] && !array_is_list($remote) && !isset($remote['error'])) {
            $balance = (float) ($remote['balance'] ?? $remote['token_balance'] ?? 0);
            $available = (float) ($remote['available'] ?? $remote['tokens_available'] ?? $balance);
            $cost = array_key_exists('token_cost', $remote)
                ? (float) $remote['token_cost']
                : (array_key_exists('credit_cost', $remote) ? (float) $remote['credit_cost'] : null);

            return [
                'can_use' => (bool) ($remote['can_use'] ?? $remote['has_access'] ?? false),
                'reason' => (string) ($remote['reason'] ?? 'OK'),
                'has_subscription' => (bool) ($remote['has_subscription'] ?? false),
                'token_cost' => $cost,
                'available_balance' => $available,
                'balance' => $balance,
            ];
        }

        $available = $this->tokenService->getAvailableBalance($orgId);
        $balance   = $this->tokenService->getBalance($orgId);
        $hasSub    = $this->hasActiveSubscription($orgId);
        $cost      = $this->getProductTokenCost($productSlug);

        if ($cost === null) {
            return [
                'can_use' => false,
                'reason' => 'Unknown product',
                'has_subscription' => $hasSub,
                'token_cost' => null,
                'available_balance' => $available,
                'balance' => $balance,
            ];
        }

        return [
            'can_use' => $hasSub && $available >= $cost,
            'reason' => !$hasSub ? 'No active subscription' : ($available < $cost ? 'Insufficient tokens' : 'OK'),
            'has_subscription' => $hasSub,
            'token_cost' => $cost,
            'available_balance' => $available,
            'balance' => $balance,
        ];
    }

    public function checkTokens(string $orgId, float $requiredTokens): bool
    {
        if (!$this->hasActiveSubscription($orgId)) {
            return false;
        }

        return $this->tokenService->getAvailableBalance($orgId) >= $requiredTokens;
    }

    public function checkCredit(string $orgId, float $requiredCredits): bool
    {
        return $this->checkTokens($orgId, $requiredCredits);
    }

    public function checkApiKey(string $apiKey, string $productSlug): bool
    {
        $userApiUrl = trim((string) ($_ENV['USER_API_URL'] ?? ''));
        $validateUrl = $userApiUrl !== '' ? $userApiUrl : $this->operationsApiUrl;
        $validatePath = $userApiUrl !== '' ? '/api-keys/validate' : '/apikeys/validate';

        $resp = ApiClient::post($validateUrl, $validatePath, [
            'api_key' => $apiKey,
            'product_slug' => $productSlug,
        ]);
        $data = $this->unwrap($resp);
        if (array_key_exists('valid', $data)) {
            return (bool) $data['valid'];
        }

        $key = $this->apiKeyService->findByKey($apiKey);
        if (!$key || empty($key['organisation_id'])) {
            return false;
        }

        return $this->checkAccess((string) $key['organisation_id'], $productSlug);
    }

    public function authorizeJob(string $orgId, string $productSlug, float $estimatedTokens): array
    {
        $resp = ApiClient::post($this->operationsApiUrl, '/authorize', [
            'org_id' => $orgId,
            'product_slug' => $productSlug,
            'estimated_tokens' => $estimatedTokens,
        ]);
        $remote = $this->unwrap($resp);
        if (is_array($remote) && array_key_exists('authorized', $remote)) {
            return $remote;
        }

        $evaluation = $this->evaluateProductAccess($orgId, $productSlug);
        $balance    = $evaluation['balance'];
        $available  = $evaluation['available_balance'];
        $cost       = $evaluation['token_cost'];

        if ($cost === null) {
            return [
                'authorized' => false,
                'reason' => 'Unknown product',
                'tokenBalance' => $balance,
                'token_cost' => null,
                'available' => $available,
                'creditBalance' => $balance,
                'credit_cost' => null,
            ];
        }

        $required = $estimatedTokens > 0 ? $estimatedTokens : $cost;
        $authorized = $evaluation['has_subscription'] && $available >= $required;

        return [
            'authorized' => $authorized,
            'reason' => $authorized ? 'OK' : (!$evaluation['has_subscription'] ? 'No active subscription' : 'Insufficient tokens'),
            'tokenBalance' => $balance,
            'token_cost' => $cost,
            'available' => $available,
            'creditBalance' => $balance,
            'credit_cost' => $cost,
        ];
    }
}
