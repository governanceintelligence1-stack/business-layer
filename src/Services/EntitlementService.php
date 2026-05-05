<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class EntitlementService
{
    private DB $db;
    private SubscriptionService $subscriptionService;
    private CreditService $creditService;
    private ApiKeyService $apiKeyService;

    public function __construct()
    {
        $this->db                  = DB::getInstance();
        $this->subscriptionService = new SubscriptionService();
        $this->creditService       = new CreditService();
        $this->apiKeyService       = new ApiKeyService();
    }

    public function checkAccess(string $orgId, string $productSlug): bool
    {
        $sub = $this->subscriptionService->getActive($orgId);
        if (!$sub) {
            return false;
        }

        $result = $this->db->fetch(
            'SELECT pp.id FROM plan_products pp
             INNER JOIN products p ON pp.product_id = p.id
             WHERE pp.plan_id = :plan_id AND p.slug = :slug',
            ['plan_id' => $sub['plan_id'], 'slug' => $productSlug]
        );

        return $result !== false;
    }

    public function checkCredit(string $orgId, float $requiredCredits): bool
    {
        return $this->creditService->getAvailableBalance($orgId) >= $requiredCredits;
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

    public function authorizeJob(string $orgId, string $productSlug, float $estimatedCredits): array
    {
        if (!$this->checkAccess($orgId, $productSlug)) {
            return [
                'authorized'    => false,
                'reason'        => 'No active subscription for this product',
                'creditBalance' => $this->creditService->getBalance($orgId),
            ];
        }

        $balance = $this->creditService->getAvailableBalance($orgId);

        if ($balance < $estimatedCredits) {
            return [
                'authorized'    => false,
                'reason'        => 'Insufficient credits',
                'creditBalance' => $balance,
            ];
        }

        return [
            'authorized'    => true,
            'reason'        => 'OK',
            'creditBalance' => $balance,
        ];
    }
}
