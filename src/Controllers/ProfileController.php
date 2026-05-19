<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\UserService;

class ProfileController
{
    public function index(): void
    {
        Middleware::auth();
        $sessionUser = Session::get('user') ?? [];
        $user = $sessionUser;

        try {
            $userService = new UserService();
            if (!empty($sessionUser['id'])) {
                $dbUser = $userService->findById((string)$sessionUser['id']);
                if ($dbUser) {
                    $user = $dbUser;
                }
            } elseif (!empty($sessionUser['keycloak_id'])) {
                $dbUser = $userService->findByKeycloakId((string)$sessionUser['keycloak_id']);
                if ($dbUser) {
                    $user = $dbUser;
                }
            } elseif (!empty($sessionUser['email'])) {
                $dbUser = $userService->findByEmail((string)$sessionUser['email']);
                if ($dbUser) {
                    $user = $dbUser;
                }
            }
        } catch (\Throwable $e) {
            // Fall back to session user if DB is unreachable or query fails
        }

        View::render('profile/index', [
            'user' => $user,
        ]);
    }
}

