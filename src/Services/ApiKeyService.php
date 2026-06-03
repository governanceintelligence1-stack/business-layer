<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class ApiKeyService
{
    private string $userApiUrl;

    public function __construct()
    {
        $this->userApiUrl = rtrim((string) ($_ENV['USER_API_URL'] ?? ''), '/');
    }

    /** @return array<string, mixed>|list<mixed> */
    private function unwrap(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function listFromResponse(array $response): array
    {
        $data = $this->unwrap($response);
        return array_is_list($data) ? $data : [];
    }

    /** @return array<string, mixed>|false */
    private function itemFromResponse(array $response): array|false
    {
        $data = $this->unwrap($response);
        if (!is_array($data) || $data === [] || array_is_list($data)) {
            return false;
        }

        return $data;
    }

    public function generate(string $orgId, string $userId, string $name, string $productId = ''): array
    {
        if ($this->userApiUrl === '') {
            return [];
        }

        $resp = ApiClient::post($this->userApiUrl, '/api-keys', [
            'organisation_id' => $orgId,
            'created_by' => $userId,
            'name' => $name,
            'product_id' => $productId !== '' ? $productId : null,
        ]);
        $data = $this->unwrap($resp);

        return is_array($data) && !array_is_list($data) ? $data : [];
    }

    public function revoke(string $keyId): int
    {
        if ($this->userApiUrl === '') {
            return 0;
        }

        $resp = ApiClient::patch($this->userApiUrl, '/api-keys/' . urlencode($keyId) . '/revoke', []);
        $data = $this->unwrap($resp);

        return (int) ($data['updated'] ?? $data['revoked'] ?? 1);
    }

    public function findByKey(string $apiKey): array|false
    {
        if ($this->userApiUrl === '') {
            return false;
        }

        return $this->itemFromResponse(ApiClient::post($this->userApiUrl, '/api-keys/validate', [
            'api_key' => $apiKey,
        ]));
    }

    public function getForOrganisation(string $orgId): array
    {
        if ($this->userApiUrl === '') {
            return [];
        }

        return $this->listFromResponse(ApiClient::get(
            $this->userApiUrl,
            '/api-keys/' . urlencode($orgId)
        ));
    }

    public function logUsage(string $keyId, string $endpoint, float $creditsUsed = 0, int $responseCode = 200): void
    {
        if ($this->userApiUrl === '') {
            return;
        }

        ApiClient::post($this->userApiUrl, '/api-keys/' . urlencode($keyId) . '/usage', [
            'endpoint' => $endpoint,
            'credits_charged' => $creditsUsed,
            'response_code' => $responseCode,
        ]);
    }

    public function getUsageStats(string $apiKey): array
    {
        if ($this->userApiUrl === '') {
            return [];
        }

        $key = $this->findByKey($apiKey);
        if ($key === false || empty($key['id'])) {
            return [];
        }

        return $this->listFromResponse(ApiClient::get(
            $this->userApiUrl,
            '/api-keys/' . urlencode((string) $key['id']) . '/usage'
        ));
    }
}
