<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class PlanService
{
    private string $operationsApiUrl;

    public function __construct()
    {
        $this->operationsApiUrl = (string) ($_ENV['OPERATIONS_API_URL'] ?? '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listFromResponse(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        return array_is_list($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function itemFromResponse(array $response): array|false
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        if ($response === [] || array_is_list($response)) {
            return false;
        }
        return $response;
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @return list<array<string, mixed>>
     */
    private function activeOnly(array $plans): array
    {
        return array_values(array_filter($plans, static function (array $plan): bool {
            return strtolower((string) ($plan['status'] ?? 'active')) === 'active';
        }));
    }

    public function getAll(): array
    {
        return $this->listFromResponse(ApiClient::get($this->operationsApiUrl, '/plans'));
    }

    public function getActive(): array
    {
        $plans = $this->listFromResponse(ApiClient::get($this->operationsApiUrl, '/plans/active'));
        return $plans !== [] ? $plans : $this->activeOnly($this->getAll());
    }

    public function findById(string $id): array|false
    {
        $plan = $this->itemFromResponse(ApiClient::get($this->operationsApiUrl, '/plans/' . urlencode($id)));
        if ($plan !== false) {
            return $plan;
        }

        foreach ($this->getAll() as $candidate) {
            if ((string) ($candidate['id'] ?? '') === $id) {
                return $candidate;
            }
        }

        return false;
    }

    public function findBySlug(string $slug): array|false
    {
        $plan = $this->itemFromResponse(ApiClient::get($this->operationsApiUrl, '/plans/slug/' . urlencode($slug)));
        if ($plan !== false) {
            return $plan;
        }

        foreach ($this->getAll() as $candidate) {
            if ((string) ($candidate['slug'] ?? '') === $slug) {
                return $candidate;
            }
        }

        return false;
    }

    public function getPlanProducts(string $planId): array
    {
        return $this->listFromResponse(
            ApiClient::get($this->operationsApiUrl, '/plans/' . urlencode($planId) . '/products')
        );
    }

    /** All active platform products (every plan includes the same product set; usage is token-limited). */
    public function getPlatformProducts(): array
    {
        $products = $this->listFromResponse(ApiClient::get($this->operationsApiUrl, '/platform-products'));
        return $products !== [] ? $products : (new ProductService())->getActive();
    }

    public function getMinimumMonthlyCredits(): float
    {
        $resp = ApiClient::get($this->operationsApiUrl, '/plans/minimum-monthly-credits');
        if (isset($resp['data']) && is_array($resp['data'])) {
            return (float) ($resp['data']['total'] ?? 0);
        }
        return (float) ($resp['total'] ?? 0);
    }
}
