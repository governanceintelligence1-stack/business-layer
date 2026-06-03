<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\UserService;

class ProfileController
{
    /**
     * @param array<string, mixed> $sessionUser
     * @param array<string, mixed> $dbUser
     * @return array<string, mixed>
     */
    private function mergeProfileData(array $sessionUser, array $dbUser): array
    {
        $merged = $sessionUser;

        foreach ($dbUser as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $merged[$key] = $value;
        }

        if (!empty($dbUser['user_id']) && empty($merged['id'])) {
            $merged['id'] = $dbUser['user_id'];
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePreferences(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function index(): void
    {
        Middleware::auth();
        $sessionUser = Session::get('user') ?? [];
        $user = $sessionUser;

        try {
            $userService = new UserService();
            if (!empty($sessionUser['id'])) {
                $dbUser = $userService->getProfile((string)$sessionUser['id']);
                if (!$dbUser) {
                    $dbUser = $userService->findById((string)$sessionUser['id']);
                }
                if ($dbUser) {
                    $user = $dbUser;
                }
            } elseif (!empty($sessionUser['keycloak_id'])) {
                $dbUser = $userService->findByKeycloakId((string)$sessionUser['keycloak_id']);
                if ($dbUser) {
                    $profile = !empty($dbUser['id'])
                        ? $userService->getProfile((string)$dbUser['id'])
                        : false;
                    $user = $profile ?: $dbUser;
                }
            } elseif (!empty($sessionUser['email'])) {
                $dbUser = $userService->findByEmail((string)$sessionUser['email']);
                if ($dbUser) {
                    $profile = !empty($dbUser['id'])
                        ? $userService->getProfile((string)$dbUser['id'])
                        : false;
                    $user = $profile ?: $dbUser;
                }
            }
            $user = $this->mergeProfileData($sessionUser, $user);
            $user['preferences'] = $this->decodePreferences($user['preferences'] ?? []);
        } catch (\Throwable $e) {
            // Fall back to session user if DB is unreachable or query fails
        }

        View::render('profile/index', [
            'user' => $user,
        ]);
    }
}

