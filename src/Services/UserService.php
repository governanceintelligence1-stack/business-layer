<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class UserService
{
    private string $userApiUrl;

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

    public function findByKeycloakId(string $keycloakId): array|false
    {
        return $this->itemFromResponse(
            ApiClient::get($this->userApiUrl, '/users/keycloak/' . urlencode($keycloakId))
        );
    }

    public function findByEmail(string $email): array|false
    {
        return $this->itemFromResponse(
            ApiClient::get($this->userApiUrl, '/users/by-email', ['email' => $email])
        );
    }

    public function findByEmailForDbLogin(string $email): array|false
    {
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

        return $this->itemFromResponse(
            ApiClient::getWithHeaders($this->userApiUrl, '/users/by-email', ['email' => $email], $headers)
        );
    }

    public function getProfileForDbLogin(string $userId): array|false
    {
        $token = ApiClient::testContextBearerToken();
        $headers = $token !== '' ? ['Authorization: Bearer ' . $token] : [];

        return $this->itemFromResponse(
            ApiClient::getWithHeaders($this->userApiUrl, '/users/' . urlencode($userId) . '/profile', [], $headers)
        );
    }

    public function findById(string $id): array|false
    {
        return $this->itemFromResponse(ApiClient::get($this->userApiUrl, '/users/' . urlencode($id)));
    }

    public function create(array $data): string
    {
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            return '';
        }

        $payload = [
            'email' => $email,
            'username' => (string) ($data['username'] ?? $email),
            'sso_provider' => (string) ($data['sso_provider'] ?? 'keycloak_demo'),
            'sso_subject_id' => $data['sso_subject_id'] ?? $data['keycloak_id'] ?? null,
            'keycloak_id' => $data['keycloak_id'] ?? $data['sso_subject_id'] ?? null,
            'organisation_id' => $data['organisation_id'] ?? null,
            'email_verified' => $data['email_verified'] ?? true,
            'role' => $this->normalizeAccountRole((string) ($data['role'] ?? 'viewer')),
            'status' => (string) ($data['status'] ?? 'active'),
        ];

        $profile = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'display_name' => $data['display_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'locale' => $data['locale'] ?? null,
        ], static fn($value) => $value !== null && $value !== '');

        $resp = ApiClient::post($this->userApiUrl, '/auth/sso-sync', array_merge($payload, $profile));
        if (isset($resp['error'])) {
            return '';
        }

        $row = $this->itemFromResponse($resp);
        if ($row === false) {
            return '';
        }

        $userId = (string) ($row['id'] ?? '');
        if ($userId !== '' && $profile !== []) {
            $this->saveProfile($userId, $profile);
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveProfile(string $userId, array $data): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }

        $payload = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'display_name' => $data['display_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'locale' => $data['locale'] ?? null,
        ], static fn($value) => $value !== null && $value !== '');

        if ($payload === []) {
            return false;
        }

        $resp = ApiClient::patch(
            $this->userApiUrl,
            '/users/' . urlencode($userId) . '/profile',
            $payload
        );

        return !isset($resp['error']) && (($resp['status'] ?? '') === 'updated' || isset($resp['profile']));
    }

    private function normalizeAccountRole(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'user', 'member' => 'viewer',
            default => $role,
        };
    }

    public function update(string $id, array $data): int
    {
        $resp = ApiClient::patch($this->userApiUrl, '/users/' . urlencode($id), $data);
        $row = $this->itemFromResponse($resp);
        return (int) ($row['updated'] ?? 0);
    }

    public function getProfile(string $userId): array|false
    {
        return $this->itemFromResponse(
            ApiClient::get($this->userApiUrl, '/users/' . urlencode($userId) . '/profile')
        );
    }

    public function upsertFromKeycloak(array $kcUser, string $organisationId = ''): array
    {
        $existing = $this->findByKeycloakId($kcUser['sub'] ?? '');

        $profileFirst = trim((string)($kcUser['given_name'] ?? ''));
        $profileLast = trim((string)($kcUser['family_name'] ?? ''));

        $data = [
            'keycloak_id'     => $kcUser['sub'] ?? '',
            'email'           => $kcUser['email'] ?? '',
            'username'        => $kcUser['preferred_username'] ?? null,
            'email_verified'  => filter_var($kcUser['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sso_provider'    => 'keycloak',
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($organisationId !== '') {
            $data['organisation_id'] = $organisationId;
        }

        if ($existing) {
            if (empty($data['organisation_id'])) {
                unset($data['organisation_id']);
            }
            $this->update($existing['id'], array_merge($data, [
                'first_name' => $profileFirst,
                'last_name'  => $profileLast,
            ]));
            return array_merge($existing, $data, [
                'first_name' => $profileFirst,
                'last_name'  => $profileLast,
            ]);
        }

        $orgId = $organisationId;
        if ($orgId === '') {
            $slug = trim((string)($_ENV['DEFAULT_ORGANISATION_SLUG'] ?? 'governance-intelligence-test'));
            $org = ApiClient::get($this->userApiUrl, '/organisations/slug/' . urlencode($slug));
            if (isset($org['data']) && is_array($org['data'])) {
                $org = $org['data'];
            }
            if ($org && !empty($org['id'])) {
                $orgId = (string) $org['id'];
            }
        }
        if ($orgId === '') {
            $org = ApiClient::get($this->userApiUrl, '/organisations/default');
            if (isset($org['data']) && is_array($org['data'])) {
                $org = $org['data'];
            }
            if ($org && !empty($org['id'])) {
                $orgId = (string) $org['id'];
            }
        }
        if ($orgId === '') {
            throw new \RuntimeException(
                'No organisation available for new user. Set DEFAULT_ORGANISATION_SLUG or seed organisations.'
            );
        }

        $data['organisation_id'] = $orgId;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['role'] = 'admin';
        $data['status'] = 'active';

        $id = $this->create(array_merge($data, [
            'first_name' => $profileFirst,
            'last_name'  => $profileLast,
        ]));

        return array_merge($data, ['id' => $id, 'first_name' => $profileFirst, 'last_name' => $profileLast]);
    }
}
