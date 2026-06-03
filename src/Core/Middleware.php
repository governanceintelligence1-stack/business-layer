<?php
declare(strict_types=1);

namespace GI\Core;

use GI\Services\ApiKeyService;

class Middleware
{
    private static function resolveBypassOrganisationId(): string
    {
        $configured = trim((string) ($_ENV['AUTH_BYPASS_ORGANISATION_ID'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        try {
            $userApi = (string) ($_ENV['USER_API_URL'] ?? '');
            if ($userApi === '') {
                return (string) ($_ENV['DEV_BYPASS_ORG_ID'] ?? '');
            }

            $slug = trim((string) ($_ENV['DEFAULT_ORGANISATION_SLUG'] ?? ''));
            if ($slug !== '') {
                $org = ApiClient::get($userApi, '/organisations/slug/' . urlencode($slug));
                $row = (isset($org['data']) && is_array($org['data'])) ? $org['data'] : $org;
                if (!empty($row['id'])) {
                    return (string) $row['id'];
                }
            }

            $org = ApiClient::get($userApi, '/organisations/default');
            $row = (isset($org['data']) && is_array($org['data'])) ? $org['data'] : $org;
            return (string) ($row['id'] ?? ($_ENV['DEV_BYPASS_ORG_ID'] ?? ''));
        } catch (\Exception $e) {
            return (string) ($_ENV['DEV_BYPASS_ORG_ID'] ?? '');
        }
    }

    private static function loadBypassUserFromDatabase(string $email): ?array
    {
        try {
            $userApi = (string) ($_ENV['USER_API_URL'] ?? '');
            if ($userApi === '') {
                return null;
            }

            $response = ApiClient::get($userApi, '/users/by-email', ['email' => $email]);
            $row = (isset($response['data']) && is_array($response['data'])) ? $response['data'] : $response;
            if (!$row || empty($row['id'])) {
                return null;
            }

            $profile = ApiClient::get($userApi, '/users/' . urlencode((string) $row['id']) . '/profile');
            $profileRow = (isset($profile['data']) && is_array($profile['data'])) ? $profile['data'] : $profile;
            if (is_array($profileRow) && !empty($profileRow['user_id'])) {
                $row = array_merge($row, $profileRow, ['id' => (string) $profileRow['user_id']]);
            }

            $organisationId = (string) ($row['organisation_id'] ?? '');
            $membershipRole = '';
            if ($organisationId !== '') {
                foreach ((new \GI\Services\OrganisationService())->getMembers($organisationId) as $member) {
                    if ((string) ($member['user_id'] ?? '') !== (string) $row['id']) {
                        continue;
                    }
                    $membershipRole = (string) ($member['membership_role'] ?? $member['role'] ?? '');
                    break;
                }
            }

            $effectiveRole = $membershipRole !== '' ? $membershipRole : (string) ($row['role'] ?? 'viewer');

            return [
                'id'                => (string) $row['id'],
                'email'             => (string) ($row['email'] ?? ''),
                'first_name'        => (string) ($row['first_name'] ?? ''),
                'last_name'         => (string) ($row['last_name'] ?? ''),
                'role'              => $effectiveRole,
                'membership_role'   => $membershipRole !== '' ? $membershipRole : $effectiveRole,
                'organisation_id'   => $organisationId,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function isAuthBypassed(): bool
    {
        $value = strtolower(trim((string) ($_ENV['AUTH_BYPASS'] ?? 'false')));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function auth(): void
    {
        if (self::isAuthBypassed()) {
            $demoUser = Session::get('demo_sso_user');
            $current = Session::get('user') ?? [];
            if ($demoUser === true && is_array($current) && !empty($current['id'])) {
                return;
            }

            $configuredOrgId = trim((string) ($_ENV['AUTH_BYPASS_ORGANISATION_ID'] ?? ''));
            $organisationId = $configuredOrgId !== ''
                ? $configuredOrgId
                : (string)($current['organisation_id'] ?? '');
            if ($organisationId === '') {
                $organisationId = self::resolveBypassOrganisationId();
            }

            $email = trim((string)($_ENV['AUTH_BYPASS_USER_EMAIL'] ?? 'test.user@gismartanalytics.com'));
            $fromDb = self::loadBypassUserFromDatabase($email);
            if ($fromDb !== null) {
                if ($organisationId !== '') {
                    $fromDb['organisation_id'] = $organisationId;
                } elseif (empty($fromDb['organisation_id'])) {
                    $fromDb['organisation_id'] = $organisationId;
                }
                Session::set('user', $fromDb);
                return;
            }

            if (!Session::has('user') || strtolower((string)($current['email'] ?? '')) !== strtolower($email)) {
                Session::set('user', [
                    'id'              => 'dev-user',
                    'email'           => $email,
                    'first_name'      => 'Bypass',
                    'last_name'       => 'User',
                    'role'            => 'admin',
                    'organisation_id' => $organisationId,
                ]);
            } elseif (($current['organisation_id'] ?? '') !== $organisationId) {
                $current['organisation_id'] = $organisationId;
                Session::set('user', $current);
            }

            return;
        }

        $auth = new Auth();
        if (!$auth->isAuthenticated()) {
            Session::flash('error', 'Please log in to continue.');
            header('Location: /auth/login');
            exit;
        }
    }

    public static function guest(): void
    {
        if (self::isAuthBypassed()) {
            return;
        }

        $auth = new Auth();
        if ($auth->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }
    }

    public static function apiAuth(?string $requiredOrganisationId = null): array
    {
        $headers      = getallheaders();
        $authHeader   = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $apiKeyHeader = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
        $giApiKeyHeader = $headers['X-GI-API-Key'] ?? $headers['x-gi-api-key'] ?? '';

        if (!empty($apiKeyHeader) || !empty($giApiKeyHeader)) {
            $principal = self::apiPrincipalFromApiKey(trim((string) ($apiKeyHeader ?: $giApiKeyHeader)));
            self::assertApiOrganisation($principal, $requiredOrganisationId);
            return $principal;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', (string) $authHeader, $matches)) {
            $token = $matches[1];
            $principal = self::apiPrincipalFromJwt(trim($token));
            self::assertApiOrganisation($principal, $requiredOrganisationId);
            return $principal;
        }

        if (preg_match('/^ApiKey\s+(.+)$/i', (string) $authHeader, $matches)) {
            $principal = self::apiPrincipalFromApiKey(trim($matches[1]));
            self::assertApiOrganisation($principal, $requiredOrganisationId);
            return $principal;
        }

        ApiResponse::unauthorized();
        exit;
    }

    /**
     * @return array{type: string, value: string, organisation_id: string, user_id?: string, role?: string}
     */
    private static function apiPrincipalFromApiKey(string $apiKey): array
    {
        if ($apiKey === '') {
            ApiResponse::unauthorized();
            exit;
        }

        $record = (new ApiKeyService())->findByKey($apiKey);
        if ($record === false || empty($record['organisation_id'])) {
            ApiResponse::unauthorized('Invalid API key');
            exit;
        }

        $status = strtolower((string) ($record['status'] ?? 'active'));
        if (in_array($status, ['revoked', 'inactive', 'disabled'], true)) {
            ApiResponse::unauthorized('API key is not active');
            exit;
        }

        return [
            'type' => 'api_key',
            'value' => $apiKey,
            'organisation_id' => (string) $record['organisation_id'],
            'user_id' => (string) ($record['created_by'] ?? $record['user_id'] ?? ''),
            'role' => 'api_key',
        ];
    }

    /**
     * @return array{type: string, value: string, organisation_id: string, user_id: string, role: string}
     */
    private static function apiPrincipalFromJwt(string $token): array
    {
        $secret = trim((string) ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: ''));
        if ($token === '' || $secret === '') {
            ApiResponse::unauthorized('JWT authentication is not configured');
            exit;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            ApiResponse::unauthorized('Malformed JWT');
            exit;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        if (!is_array($header) || strtoupper((string) ($header['alg'] ?? '')) !== 'HS256') {
            ApiResponse::unauthorized('Unsupported JWT algorithm');
            exit;
        }

        $expected = self::base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true));
        if (!hash_equals($expected, $encodedSignature)) {
            ApiResponse::unauthorized('Invalid JWT signature');
            exit;
        }

        $claims = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!is_array($claims)) {
            ApiResponse::unauthorized('Invalid JWT payload');
            exit;
        }

        $now = time();
        if (isset($claims['exp']) && (int) $claims['exp'] < $now) {
            ApiResponse::unauthorized('JWT has expired');
            exit;
        }
        if (isset($claims['nbf']) && (int) $claims['nbf'] > $now) {
            ApiResponse::unauthorized('JWT is not yet valid');
            exit;
        }

        $organisationId = (string) ($claims['organisation_id'] ?? $claims['org_id'] ?? '');
        $userId = (string) ($claims['user_id'] ?? $claims['sub'] ?? '');
        if ($organisationId === '' || $userId === '') {
            ApiResponse::unauthorized('JWT must include user_id/sub and organisation_id/org_id');
            exit;
        }

        return [
            'type' => 'bearer',
            'value' => $token,
            'organisation_id' => $organisationId,
            'user_id' => $userId,
            'role' => (string) ($claims['role'] ?? 'viewer'),
        ];
    }

    /**
     * @param array<string, mixed> $principal
     */
    private static function assertApiOrganisation(array $principal, ?string $requiredOrganisationId): void
    {
        $requiredOrganisationId = trim((string) $requiredOrganisationId);
        if ($requiredOrganisationId === '') {
            return;
        }

        $principalOrgId = (string) ($principal['organisation_id'] ?? '');
        if ($principalOrgId === '' || !hash_equals($principalOrgId, $requiredOrganisationId)) {
            ApiResponse::forbidden('API credential cannot access this organisation.');
            exit;
        }
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function csrfCheck(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if (!hash_equals(Session::getCsrfToken(), $token)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'CSRF token mismatch']);
                exit;
            }
        }
    }
}
