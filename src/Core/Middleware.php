<?php
declare(strict_types=1);

namespace GI\Core;

class Middleware
{
    public static function auth(): void
    {
        $auth = new Auth();
        if (!$auth->isAuthenticated()) {
            Session::flash('error', 'Please log in to continue.');
            header('Location: /auth/login');
            exit;
        }
    }

    public static function guest(): void
    {
        $auth = new Auth();
        if ($auth->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }
    }

    public static function apiAuth(): ?array
    {
        $headers      = getallheaders();
        $authHeader   = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $apiKeyHeader = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';

        if (!empty($apiKeyHeader)) {
            return ['type' => 'api_key', 'value' => $apiKeyHeader];
        }

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return ['type' => 'bearer', 'value' => $token];
        }

        ApiResponse::unauthorized();
        exit;
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
