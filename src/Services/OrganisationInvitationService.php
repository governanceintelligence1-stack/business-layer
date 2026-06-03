<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class OrganisationInvitationService
{
    private string $userApiUrl;

    public function __construct()
    {
        $this->userApiUrl = (string) ($_ENV['USER_API_URL'] ?? '');
    }

    /** @return array<string, mixed>|list<mixed> */
    private function unwrap(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    /** @return array<string, mixed>|false */
    private function itemFromResponse(array $response): array|false
    {
        $data = $this->unwrap($response);
        if (!is_array($data) || $data === [] || array_is_list($data)) {
            return false;
        }

        if (isset($data['error']) && !isset($data['id']) && !isset($data['invitation'])) {
            return false;
        }

        return $data;
    }

    /** @return array<string, mixed>|false */
    private function invitationFromResponse(array $response): array|false
    {
        $data = $this->unwrap($response);
        if (!is_array($data)) {
            return false;
        }

        if (isset($data['invitation']) && is_array($data['invitation'])) {
            $invite = $data['invitation'];
            if (isset($data['error'])) {
                $invite['_api_error'] = (string) $data['error'];
            }

            return $invite;
        }

        if (isset($data['error']) && !isset($data['id'])) {
            return false;
        }

        if ($data === [] || array_is_list($data)) {
            return false;
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function listFromResponse(array $response): array
    {
        $data = $this->unwrap($response);
        if (isset($data['invitations']) && is_array($data['invitations'])) {
            return array_is_list($data['invitations']) ? $data['invitations'] : [];
        }

        return array_is_list($data) ? $data : [];
    }

    /** @return list<array<string, mixed>> */
    public function getForOrganisation(string $orgId): array
    {
        return $this->listFromResponse(ApiClient::get(
            $this->userApiUrl,
            '/organisations/' . urlencode($orgId) . '/invitations'
        ));
    }

    /** @return array<string, mixed>|false */
    public function create(string $orgId, string $email, string $role, string $invitedBy): array|false
    {
        return $this->itemFromResponse(ApiClient::post(
            $this->userApiUrl,
            '/organisations/' . urlencode($orgId) . '/invitations',
            [
                'invited_email' => $email,
                'email' => $email,
                'invited_role' => $role,
                'role' => $role,
                'invited_by' => $invitedBy !== '' ? $invitedBy : null,
                'invite_url_base' => rtrim((string) ($_ENV['APP_URL'] ?? ''), '/'),
            ]
        ));
    }

    public function cancel(string $orgId, string $inviteId): bool
    {
        $data = $this->unwrap(ApiClient::patch(
            $this->userApiUrl,
            '/organisations/' . urlencode($orgId) . '/invitations/' . urlencode($inviteId) . '/cancel',
            []
        ));

        return (bool) ($data['cancelled'] ?? $data['updated'] ?? ($data['status'] ?? '') === 'cancelled');
    }

    /** @return array<string, mixed>|false */
    public function findByToken(string $token): array|false
    {
        return $this->invitationFromResponse(ApiClient::get(
            $this->userApiUrl,
            '/invitations/' . urlencode($token)
        ));
    }

    /** @return array<string, mixed>|false */
    public function accept(string $token, array $user, array $profile = []): array|false
    {
        if ($profile === []) {
            $profile = array_filter([
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'phone_number' => $user['phone_number'] ?? null,
                'display_name' => trim(
                    (string) ($user['display_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))
                ) ?: null,
            ], static fn($value) => $value !== null && $value !== '');
        }

        $response = ApiClient::post(
            $this->userApiUrl,
            '/invitations/' . urlencode($token) . '/accept',
            [
                'user_id' => $user['id'] ?? $user['user_id'] ?? null,
                'sso_subject_id' => $user['sso_subject_id'] ?? $user['keycloak_id'] ?? null,
                'keycloak_id' => $user['keycloak_id'] ?? $user['sso_subject_id'] ?? null,
                'email' => $user['email'] ?? null,
                'email_verified' => (bool) ($user['email_verified'] ?? true),
                'profile' => $profile,
            ]
        );

        $accepted = $this->invitationFromResponse($response);
        if ($accepted !== false) {
            return $accepted;
        }

        return $this->itemFromResponse($response);
    }
}
