<?php
declare(strict_types=1);

namespace GI\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        ini_set('session.cookie_httponly', '1');
        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
        ini_set('session.cookie_secure', $isProduction ? '1' : '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name('gi_session');

        session_start([
            'cookie_lifetime' => 0,
            'gc_maxlifetime'  => 3600,
        ]);

        self::$started = true;

        $secret = $_ENV['SESSION_SECRET'] ?? '';
        if ($secret === '') {
            throw new \RuntimeException('SESSION_SECRET environment variable must be set');
        }
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = hash_hmac('sha256', uniqid('', true), $secret);
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        session_destroy();
        self::$started = false;
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function getCsrfToken(): string
    {
        return $_SESSION['_token'] ?? '';
    }
}
