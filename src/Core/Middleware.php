<?php
declare(strict_types=1);

namespace GI\Core;

class Middleware
{
    private static function resolveBypassOrganisationId(): string
    {
        try {
            $db = DB::getInstance();
            $existing = $db->fetch('SELECT id FROM organisations ORDER BY created_at ASC LIMIT 1');
            if (!empty($existing['id'])) {
                return (string)$existing['id'];
            }

            // Seed a deterministic dev org when auth bypass is enabled and DB is empty.
            $slug = 'dev-bypass-org';
            $foundBySlug = $db->fetch('SELECT id FROM organisations WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            if (!empty($foundBySlug['id'])) {
                return (string)$foundBySlug['id'];
            }

            return $db->insert('organisations', [
                'name' => 'Dev Bypass Organisation',
                'slug' => $slug,
                'account_type' => 'company',
                'country' => 'ZA',
                'status' => 'active',
            ]);
        } catch (\Exception $e) {
            return '';
        }
    }

    private static function isAuthBypassed(): bool
    {
        $value = strtolower(trim((string) ($_ENV['AUTH_BYPASS'] ?? 'false')));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function auth(): void
    {
        if (self::isAuthBypassed()) {
            $current = Session::get('user') ?? [];
            $organisationId = (string)($current['organisation_id'] ?? '');
            if ($organisationId === '') {
                $organisationId = self::resolveBypassOrganisationId();
            }

            if (!Session::has('user')) {
                Session::set('user', [
                    'id'              => 'dev-user',
                    'email'           => 'designer@local.test',
                    'first_name'      => 'Design',
                    'last_name'       => 'Mode',
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
