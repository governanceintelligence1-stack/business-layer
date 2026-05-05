<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Auth;
use GI\Core\Session;
use GI\Core\View;
use GI\Core\Middleware;
use GI\Services\UserService;
use GI\Services\OrganisationService;
use GI\Services\CreditService;

class AuthController
{
    public function login(): void
    {
        Middleware::guest();
        $auth     = new Auth();
        $loginUrl = $auth->getLoginUrl();
        header('Location: ' . $loginUrl);
        exit;
    }

    public function callback(): void
    {
        $code  = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';

        if (empty($code)) {
            Session::flash('error', 'Authorization code missing.');
            header('Location: /auth/login');
            exit;
        }

        $auth = new Auth();

        if (!$auth->validateState($state)) {
            Session::flash('error', 'Invalid state parameter.');
            header('Location: /auth/login');
            exit;
        }

        try {
            $tokens   = $auth->handleCallback($code);
            $userInfo = $auth->getUserInfo($tokens['access_token']);

            $userService = new UserService();
            $user        = $userService->upsertFromKeycloak($userInfo);

            $auth->setUserSession(
                array_merge($user, ['keycloak_info' => $userInfo]),
                $tokens['access_token'],
                $tokens['id_token'] ?? ''
            );

            header('Location: /dashboard');
            exit;
        } catch (\Exception $e) {
            Session::flash('error', 'Authentication failed: ' . $e->getMessage());
            header('Location: /auth/login');
            exit;
        }
    }

    public function logout(): void
    {
        $auth    = new Auth();
        $idToken = Session::get('id_token', '');
        $auth->clearSession();
        Session::destroy();

        $logoutUrl = $auth->getLogoutUrl($idToken);
        header('Location: ' . $logoutUrl);
        exit;
    }

    public function register(): void
    {
        Middleware::guest();
        View::render('auth/register', [], 'public');
    }

    public function registerPost(): void
    {
        Middleware::guest();

        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $orgName     = trim($_POST['organisation_name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $country     = trim($_POST['country'] ?? '');
        $accountType = trim($_POST['account_type'] ?? 'individual');

        if (empty($firstName) || empty($lastName) || empty($email) || empty($orgName)) {
            Session::flash('error', 'Please fill in all required fields.');
            header('Location: /auth/register');
            exit;
        }

        try {
            $orgService    = new OrganisationService();
            $userService   = new UserService();
            $creditService = new CreditService();

            $orgId = $orgService->create([
                'name'         => $orgName,
                'account_type' => $accountType,
                'phone'        => $phone,
                'country'      => $country,
            ]);

            $userService->create([
                'keycloak_id'     => 'pending-' . uniqid(),
                'organisation_id' => $orgId,
                'email'           => $email,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'role'            => 'admin',
                'status'          => 'pending',
            ]);

            $creditService->getOrCreateAccount($orgId);

            Session::flash('success', 'Registration successful. Please log in via Keycloak.');
            header('Location: /auth/login');
            exit;
        } catch (\Exception $e) {
            Session::flash('error', 'Registration failed: ' . $e->getMessage());
            header('Location: /auth/register');
            exit;
        }
    }
}
