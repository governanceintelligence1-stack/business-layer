<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class OrganisationService
{
    private string $userApiUrl;
    private string $lastError = '';

    public function __construct()
    {
        $this->userApiUrl = (string) ($_ENV['USER_API_URL'] ?? '');
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

    public function findById(string $id): array|false
    {
        return $this->itemFromResponse(ApiClient::get($this->userApiUrl, '/organisations/' . urlencode($id)));
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->itemFromResponse(ApiClient::get($this->userApiUrl, '/organisations/slug/' . urlencode($slug)));
    }

    public function create(array $data): string
    {
        $this->lastError = '';
        $resp = ApiClient::post($this->userApiUrl, '/organisations', $data);
        $httpCode = (int) ($resp['_http_code'] ?? 0);
        if ($httpCode >= 400 || isset($resp['error'])) {
            $this->lastError = (string) ($resp['message'] ?? $resp['error'] ?? 'organisation_create_failed');
            return '';
        }

        $row = $this->itemFromResponse($resp);
        return $row === false ? '' : (string) ($row['id'] ?? '');
    }

    public function createForDemoRegistration(array $data): string
    {
        $this->lastError = '';
        $userId = trim((string) ($_ENV['AUTH_TEST_USER_ID'] ?? '22222222-2222-2222-2222-222222222222'));
        $orgId = trim((string) ($_ENV['AUTH_TEST_ORGANISATION_ID'] ?? $_ENV['AUTH_BYPASS_ORGANISATION_ID'] ?? '11111111-1111-1111-1111-111111111111'));
        $role = trim((string) ($_ENV['AUTH_TEST_ROLE'] ?? 'owner'));
        $token = ApiClient::testContextBearerToken($userId, $orgId, $role);
        $headers = $token !== ''
            ? ['Authorization: Bearer ' . $token]
            : [
                'X-Test-User-Id: ' . $userId,
                'X-Test-Organisation-Id: ' . $orgId,
                'X-Test-Role: ' . $role,
            ];

        $resp = ApiClient::postWithHeaders($this->userApiUrl, '/organisations', $data, $headers);
        $httpCode = (int) ($resp['_http_code'] ?? 0);
        if ($httpCode >= 400 || isset($resp['error'])) {
            $this->lastError = (string) ($resp['message'] ?? $resp['error'] ?? 'organisation_create_failed');
            return '';
        }

        $row = $this->itemFromResponse($resp);
        return $row === false ? '' : (string) ($row['id'] ?? '');
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function update(string $id, array $data): int
    {
        $resp = ApiClient::patch($this->userApiUrl, '/organisations/' . urlencode($id), $data);
        $row = $this->itemFromResponse($resp);
        return (int) ($row['updated'] ?? 0);
    }

    public function getMembers(string $orgId): array
    {
        $resp = ApiClient::get($this->userApiUrl, '/organisations/' . urlencode($orgId) . '/members');
        if (isset($resp['data']) && is_array($resp['data'])) {
            return $resp['data'];
        }
        return array_is_list($resp) ? $resp : [];
    }

    public function addMember(string $orgId, string $email, string $role = 'viewer'): bool
    {
        $resp = ApiClient::post($this->userApiUrl, '/organisations/' . urlencode($orgId) . '/members', [
            'email' => $email,
            'role' => $role,
        ]);
        $row = $this->itemFromResponse($resp);
        return (bool) ($row['added'] ?? false);
    }

    public function updateMember(string $membershipId, array $data): bool
    {
        $resp = ApiClient::patch($this->userApiUrl, '/memberships/' . urlencode($membershipId), $data);
        $httpCode = (int) ($resp['_http_code'] ?? 0);
        if ($httpCode >= 400 || isset($resp['error'])) {
            return false;
        }

        $row = $this->itemFromResponse($resp);
        if ($row === false) {
            return $httpCode >= 200 && $httpCode < 300;
        }

        return ($row['status'] ?? '') === 'updated' || !empty($row['id']);
    }

    public function removeMember(string $membershipId): bool
    {
        $resp = ApiClient::delete($this->userApiUrl, '/memberships/' . urlencode($membershipId));
        $row = $this->itemFromResponse($resp);
        return (bool) ($row['deleted'] ?? $row['removed'] ?? ($row['status'] ?? '') === 'removed');
    }
}
