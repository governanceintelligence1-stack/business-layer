<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class ProductService
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
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    private function activeOnly(array $products): array
    {
        return array_values(array_filter($products, static function (array $product): bool {
            return strtolower((string) ($product['status'] ?? 'active')) === 'active';
        }));
    }

    public function getAll(): array
    {
        return $this->listFromResponse(ApiClient::get($this->operationsApiUrl, '/products'));
    }

    public function getActive(): array
    {
        $products = $this->listFromResponse(ApiClient::get($this->operationsApiUrl, '/products/active'));
        return $products !== [] ? $products : $this->activeOnly($this->getAll());
    }

    public function findById(string $id): array|false
    {
        $product = $this->itemFromResponse(ApiClient::get($this->operationsApiUrl, '/products/' . urlencode($id)));
        if ($product !== false) {
            return $product;
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
        $product = $this->itemFromResponse(ApiClient::get($this->operationsApiUrl, '/products/slug/' . urlencode($slug)));
        if ($product !== false) {
            return $product;
        }

        foreach ($this->getAll() as $candidate) {
            if ((string) ($candidate['slug'] ?? '') === $slug) {
                return $candidate;
            }
        }

        return false;
    }
}
