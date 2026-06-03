<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class ArticleService
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
            return array_is_list($response['data']) ? $response['data'] : [];
        }

        return array_is_list($response) ? $response : [];
    }

    /**
     * All published articles, newest first.
     */
    public function getPublishedAll(): array
    {
        return $this->listFromResponse(ApiClient::get($this->operationsApiUrl, '/articles', [
            'status' => 'published',
        ]));
    }

    /**
     * Recent published articles for dashboard / previews.
     */
    public function getPublishedRecent(int $limit = 4): array
    {
        $limit = max(1, min(20, $limit));
        $rows = $this->listFromResponse(ApiClient::get($this->operationsApiUrl, '/articles', [
            'status' => 'published',
            'limit' => $limit,
        ]));

        return array_slice($rows, 0, $limit);
    }
}
