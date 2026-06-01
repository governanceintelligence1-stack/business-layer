<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class EntitlementService
{
    private DB $db;
    private SubscriptionService $subscriptionService;
    private TokenService $tokenService;
    private ApiKeyService $apiKeyService;
    private ProductService $productService;

    public function __construct()
    {
        $this->db                  = DB::getInstance();
        $this->subscriptionService = new SubscriptionService();
        $this->tokenService        = new TokenService();
        $this->apiKeyService       = new ApiKeyService();
        $this->productService      = new ProductService();
    }

    /**
     * Minimum monthly tokens so every active product can be used at least once.
     */
    public function getMinimumMonthlyTokens(): float
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(credit_cost), 0) AS total
             FROM products
             WHERE status = 'active'"
        );

        return (float) ($row['total'] ?? 0);
    }

    public function hasActiveSubscription(string $orgId): bool
    {
        return $this->subscriptionService->getActive($orgId) !== false;
    }

    /** Per-use token cost from products.credit_cost (DB column name unchanged). */
    public function getProductTokenCost(string $productSlug): ?float
    {
        $product = $this->productService->findBySlug($productSlug);
        if (!$product) {
            return null;
        }

        return (float) ($product['credit_cost'] ?? 0);
    }

    /** @deprecated Use getProductTokenCost() */
    public function getProductCreditCost(string $productSlug): ?float
    {
        return $this->getProductTokenCost($productSlug);
    }

    public function checkAccess(string $orgId, string $productSlug): bool
    {
        return $this->evaluateProductAccess($orgId, $productSlug)['can_use'];
    }

    /**
     * @return array{
     *   can_use: bool,
     *   reason: string,
     *   has_subscription: bool,
     *   token_cost: float|null,
     *   available_balance: float,
     *   balance: float,
     *   reserved_balance: float
     * }
     */
    public function evaluateProductAccess(string $orgId, string $productSlug): array
    {
        $summary   = $this->tokenService->getAccountSummary($orgId);
        $available = $summary['available'];
        $balance   = $summary['balance'];
        $reserved  = $summary['reserved'];
        $hasSub    = $this->hasActiveSubscription($orgId);
        $cost      = $this->getProductTokenCost($productSlug);

        if ($cost === null) {
            return [
                'can_use'           => false,
                'reason'            => 'Unknown product',
                'has_subscription'  => $hasSub,
                'token_cost'        => null,
                'available_balance' => $available,
                'balance'           => $balance,
                'reserved_balance'  => $reserved,
            ];
        }

        if (!$hasSub) {
            return [
                'can_use'           => false,
                'reason'            => 'No active subscription',
                'has_subscription'  => false,
                'token_cost'        => $cost,
                'available_balance' => $available,
                'balance'           => $balance,
                'reserved_balance'  => $reserved,
            ];
        }

        if ($available < $cost) {
            return [
                'can_use'           => false,
                'reason'            => 'Insufficient tokens',
                'has_subscription'  => true,
                'token_cost'        => $cost,
                'available_balance' => $available,
                'balance'           => $balance,
                'reserved_balance'  => $reserved,
            ];
        }

        return [
            'can_use'           => true,
            'reason'            => 'OK',
            'has_subscription'  => true,
            'token_cost'        => $cost,
            'available_balance' => $available,
            'balance'           => $balance,
            'reserved_balance'  => $reserved,
        ];
    }

    public function checkTokens(string $orgId, float $requiredTokens): bool
    {
        if (!$this->hasActiveSubscription($orgId)) {
            return false;
        }

        return $this->tokenService->getAvailableBalance($orgId) >= $requiredTokens;
    }

    /** @deprecated Use checkTokens() */
    public function checkCredit(string $orgId, float $requiredCredits): bool
    {
        return $this->checkTokens($orgId, $requiredCredits);
    }

    public function checkApiKey(string $apiKey, string $productSlug): bool
    {
        $key = $this->apiKeyService->findByKey($apiKey);
        if (!$key) {
            return false;
        }

        if (!empty($key['product_id'])) {
            $product = $this->db->fetch(
                'SELECT slug FROM products WHERE id = :id',
                ['id' => $key['product_id']]
            );
            if (!$product || $product['slug'] !== $productSlug) {
                return false;
            }
        }

        return $this->checkAccess($key['organisation_id'], $productSlug);
    }

    public function authorizeJob(string $orgId, string $productSlug, float $estimatedTokens): array
    {
        $evaluation = $this->evaluateProductAccess($orgId, $productSlug);
        $balance    = $evaluation['balance'];
        $available  = $evaluation['available_balance'];
        $reserved   = $evaluation['reserved_balance'];
        $cost       = $evaluation['token_cost'];

        if ($cost === null) {
            return [
                'authorized'           => false,
                'reason'               => 'Unknown product',
                'tokenBalance'         => $balance,
                'token_cost'           => null,
                'available'            => $available,
                'reserved'             => $reserved,
                'creditBalance'        => $balance,
                'credit_cost'          => null,
                'requires_reservation' => false,
            ];
        }

        $required = $estimatedTokens > 0 ? $estimatedTokens : $cost;

        if (!$evaluation['has_subscription']) {
            return [
                'authorized'           => false,
                'reason'               => 'No active subscription',
                'tokenBalance'         => $balance,
                'token_cost'           => $cost,
                'available'            => $available,
                'reserved'             => $reserved,
                'creditBalance'        => $balance,
                'credit_cost'          => $cost,
                'requires_reservation' => false,
            ];
        }

        if ($available < $required) {
            return [
                'authorized'           => false,
                'reason'               => 'Insufficient tokens',
                'tokenBalance'         => $balance,
                'token_cost'           => $cost,
                'available'            => $available,
                'reserved'             => $reserved,
                'creditBalance'        => $balance,
                'credit_cost'          => $cost,
                'requires_reservation' => false,
            ];
        }

        return [
            'authorized'           => true,
            'reason'               => 'OK',
            'tokenBalance'         => $balance,
            'token_cost'           => $cost,
            'available'            => $available,
            'reserved'             => $reserved,
            'creditBalance'        => $balance,
            'credit_cost'          => $cost,
            'requires_reservation' => true,
        ];
    }
}
