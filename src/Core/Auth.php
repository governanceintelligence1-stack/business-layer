<?php
declare(strict_types=1);

namespace GI\Core;

class Auth
{
    private string $keycloakUrl;
    private string $realm;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->keycloakUrl  = rtrim($_ENV['KEYCLOAK_URL'] ?? '', '/');
        $this->realm        = $_ENV['KEYCLOAK_REALM'] ?? 'gi';
        $this->clientId     = $_ENV['KEYCLOAK_CLIENT_ID'] ?? 'business-layer';
        $this->clientSecret = $_ENV['KEYCLOAK_CLIENT_SECRET'] ?? '';
        $this->redirectUri  = $_ENV['KEYCLOAK_REDIRECT_URI'] ?? '';
    }

    private function baseUrl(): string
    {
        return "{$this->keycloakUrl}/realms/{$this->realm}/protocol/openid-connect";
    }

    public function getLoginUrl(string $state = ''): string
    {
        if (empty($state)) {
            $state = bin2hex(random_bytes(16));
        }
        Session::set('oauth_state', $state);

        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid profile email',
            'state'         => $state,
        ]);

        return $this->baseUrl() . '/auth?' . $params;
    }

    public function handleCallback(string $code): array
    {
        $tokenUrl = $this->baseUrl() . '/token';

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code'          => $code,
                'redirect_uri'  => $this->redirectUri,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new \RuntimeException('Failed to exchange authorization code for tokens');
        }

        $tokens = json_decode((string) $response, true);
        if (!isset($tokens['access_token'])) {
            throw new \RuntimeException('Invalid token response from Keycloak');
        }

        return $tokens;
    }

    public function getUserInfo(string $accessToken): array
    {
        $userInfoUrl = $this->baseUrl() . '/userinfo';

        $ch = curl_init($userInfoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new \RuntimeException('Failed to fetch user info from Keycloak');
        }

        return json_decode((string) $response, true) ?? [];
    }

    public function getLogoutUrl(string $idToken = ''): string
    {
        $params = [
            'client_id'                => $this->clientId,
            'post_logout_redirect_uri' => $_ENV['APP_URL'] ?? '/',
        ];
        if (!empty($idToken)) {
            $params['id_token_hint'] = $idToken;
        }
        return $this->baseUrl() . '/logout?' . http_build_query($params);
    }

    public function isAuthenticated(): bool
    {
        return Session::has('user') && Session::has('access_token');
    }

    public function getUser(): ?array
    {
        return Session::get('user');
    }

    public function setUserSession(array $user, string $accessToken, string $idToken = ''): void
    {
        Session::set('user', $user);
        Session::set('access_token', $accessToken);
        if (!empty($idToken)) {
            Session::set('id_token', $idToken);
        }
    }

    public function clearSession(): void
    {
        Session::remove('user');
        Session::remove('access_token');
        Session::remove('id_token');
        Session::remove('oauth_state');
    }

    public function validateState(string $state): bool
    {
        $stored = Session::get('oauth_state');
        return !empty($stored) && hash_equals($stored, $state);
    }

    public function introspectToken(string $token): array
    {
        $url = $this->baseUrl() . '/token/introspect';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'token'         => $token,
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?? [];
    }
}
