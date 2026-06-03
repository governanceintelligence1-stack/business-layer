<?php
declare(strict_types=1);

namespace GI\Core;

class ApiClient
{
    /**
     * @param array<string, scalar|bool|null> $query
     * @return array<string, mixed>|list<mixed>
     */
    public static function get(string $baseUrl, string $endpoint, array $query = []): array
    {
        $url = self::buildUrl($baseUrl, $endpoint, $query);
        return self::request('GET', $url, null);
    }

    /**
     * @param array<string, scalar|bool|null> $query
     * @param list<string> $headers
     * @return array<string, mixed>|list<mixed>
     */
    public static function getWithHeaders(string $baseUrl, string $endpoint, array $query, array $headers): array
    {
        $url = self::buildUrl($baseUrl, $endpoint, $query);
        return self::request('GET', $url, null, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|list<mixed>
     */
    public static function post(string $baseUrl, string $endpoint, array $data): array
    {
        $url = self::buildUrl($baseUrl, $endpoint);
        return self::request('POST', $url, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $headers
     * @return array<string, mixed>|list<mixed>
     */
    public static function postWithHeaders(string $baseUrl, string $endpoint, array $data, array $headers): array
    {
        $url = self::buildUrl($baseUrl, $endpoint);
        return self::request('POST', $url, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|list<mixed>
     */
    public static function put(string $baseUrl, string $endpoint, array $data): array
    {
        $url = self::buildUrl($baseUrl, $endpoint);
        return self::request('PUT', $url, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|list<mixed>
     */
    public static function patch(string $baseUrl, string $endpoint, array $data): array
    {
        $url = self::buildUrl($baseUrl, $endpoint);
        return self::request('PATCH', $url, $data);
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    public static function delete(string $baseUrl, string $endpoint): array
    {
        $url = self::buildUrl($baseUrl, $endpoint);
        return self::request('DELETE', $url, null);
    }

    /**
     * @param array<string, scalar|bool|null> $query
     */
    private static function buildUrl(string $baseUrl, string $endpoint, array $query = []): string
    {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $url;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|list<mixed>
     */
    private static function request(string $method, string $url, ?array $body, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], self::platformAuthHeaders(), $extraHeaders);

        $timeout = (float) ($_ENV['SERVICE_API_TIMEOUT_SECONDS'] ?? 3);
        if ($timeout <= 0) {
            $timeout = 3;
        }

        $options = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers) . "\r\n",
                'ignore_errors' => true,
                'timeout'       => $timeout,
            ],
        ];

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_SLASHES);
            $options['http']['content'] = $json === false ? '{}' : $json;
        }

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        $httpCode = self::extractHttpStatusCode($http_response_header ?? null);

        if ($response === false) {
            return ['_http_code' => $httpCode > 0 ? $httpCode : 0, 'error' => 'request_failed'];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['_http_code' => $httpCode > 0 ? $httpCode : 0];
        }

        if (!array_is_list($decoded)) {
            $decoded['_http_code'] = $httpCode > 0 ? $httpCode : ($decoded['_http_code'] ?? 0);
        }

        return $decoded;
    }

    /**
     * @param list<string>|null $headers
     */
    private static function extractHttpStatusCode(?array $headers): int
    {
        if ($headers === null) {
            return 0;
        }

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * Forward test/session identity so platform APIs can authorize server-side calls.
     *
     * @return list<string>
     */
    private static function platformAuthHeaders(): array
    {
        $defaultTestContextAllowed = self::envFlag('AUTH_TEST_HEADERS_ENABLED', false);

        $userId = $defaultTestContextAllowed ? trim((string) ($_ENV['AUTH_TEST_USER_ID'] ?? '')) : '';
        $orgId = $defaultTestContextAllowed ? trim((string) ($_ENV['AUTH_TEST_ORGANISATION_ID'] ?? '')) : '';
        $role = $defaultTestContextAllowed ? trim((string) ($_ENV['AUTH_TEST_ROLE'] ?? 'owner')) : '';

        if (class_exists(\GI\Core\Session::class)) {
            $user = \GI\Core\Session::get('user');
            if (is_array($user)) {
                $userId = trim((string) ($user['id'] ?? $userId));
                $orgId = trim((string) ($user['organisation_id'] ?? $orgId));
                $role = trim((string) ($user['membership_role'] ?? $user['role'] ?? $role));
            }
        }

        $lines = [];
        if ($userId !== '') {
            $lines[] = 'X-Test-User-Id: ' . $userId;
        }
        if ($orgId !== '') {
            $lines[] = 'X-Test-Organisation-Id: ' . $orgId;
        }
        if ($role !== '') {
            $lines[] = 'X-Test-Role: ' . $role;
        }

        $serviceKey = trim((string) (
            $_ENV['INTERNAL_SERVICE_KEY']
            ?? getenv('INTERNAL_SERVICE_KEY')
            ?: ''
        ));
        if ($serviceKey !== '') {
            $lines[] = 'X-GI-Service-Key: ' . $serviceKey;
        }

        return $lines;
    }

    private static function envFlag(string $key, bool $default = false): bool
    {
        $raw = $_ENV[$key] ?? getenv($key);
        if ($raw === false || $raw === null || $raw === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function testContextBearerToken(?string $userId = null, ?string $orgId = null, ?string $role = null): string
    {
        $secret = trim((string) ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: ''));
        if ($secret === '') {
            return '';
        }

        $userId = trim((string) ($userId ?? ($_ENV['AUTH_TEST_USER_ID'] ?? '')));
        $orgId = trim((string) ($orgId ?? ($_ENV['AUTH_TEST_ORGANISATION_ID'] ?? $_ENV['AUTH_BYPASS_ORGANISATION_ID'] ?? '')));
        $role = trim((string) ($role ?? ($_ENV['AUTH_TEST_ROLE'] ?? 'owner')));
        if ($userId === '' || $orgId === '') {
            return '';
        }

        $now = time();
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES) ?: '{}');
        $payload = self::base64UrlEncode(json_encode([
            'sub' => $userId,
            'user_id' => $userId,
            'organisation_id' => $orgId,
            'org_id' => $orgId,
            'role' => $role !== '' ? $role : 'owner',
            'iat' => $now,
            'exp' => $now + 300,
        ], JSON_UNESCAPED_SLASHES) ?: '{}');
        $signature = self::base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $secret, true));

        return $header . '.' . $payload . '.' . $signature;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
