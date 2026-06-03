<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Auth;
use GI\Core\Session;
use GI\Core\View;
use GI\Core\Middleware;
use GI\Services\UserService;
use GI\Services\OrganisationService;
use GI\Services\OrganisationInvitationService;
use GI\Services\TokenService;

class AuthController
{
    private function demoSsoEnabled(): bool
    {
        $value = strtolower(trim((string) ($_ENV['DEMO_SSO_ENABLED'] ?? '')));
        return Middleware::isAuthBypassed() || in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function dbLoginEnabled(): bool
    {
        $value = strtolower(trim((string) ($_ENV['DB_LOGIN_ENABLED'] ?? '')));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function setDemoUserSession(array $user): void
    {
        Session::set('user', $user);
        Session::set('access_token', 'demo-sso-access-token');
        Session::set('id_token', 'demo-sso-id-token');
        Session::set('demo_sso_user', true);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function normalizeDemoUser(array $user, string $email): array
    {
        $id = (string) ($user['id'] ?? $user['user_id'] ?? '');
        return array_merge($user, [
            'id' => $id,
            'user_id' => (string) ($user['user_id'] ?? $id),
            'email' => (string) ($user['email'] ?? $email),
            'email_verified' => (bool) ($user['email_verified'] ?? true),
            'sso_provider' => (string) ($user['sso_provider'] ?? 'keycloak_demo'),
            'sso_subject_id' => (string) ($user['sso_subject_id'] ?? $user['keycloak_id'] ?? ('demo-' . sha1($email))),
            'keycloak_id' => (string) ($user['keycloak_id'] ?? $user['sso_subject_id'] ?? ('demo-' . sha1($email))),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'role' => (string) ($user['membership_role'] ?? $user['role'] ?? 'member'),
            'organisation_id' => (string) ($user['organisation_id'] ?? ''),
        ]);
    }

    private function acceptPendingInvite(array $user): bool
    {
        $token = (string) (Session::get('pending_invite_token') ?? '');
        if ($token === '') {
            return false;
        }

        $accepted = (new OrganisationInvitationService())->accept($token, $user);
        if ($accepted === false) {
            Session::flash('error', 'You signed in, but the invitation could not be accepted.');
            return false;
        }

        $organisationId = (string) (
            $accepted['organisation_id']
            ?? $accepted['membership']['organisation_id']
            ?? $accepted['organisation']['id']
            ?? ''
        );
        $role = (string) (
            $accepted['role']
            ?? $accepted['membership_role']
            ?? $accepted['membership']['role']
            ?? ''
        );
        if ($organisationId !== '') {
            $user['organisation_id'] = $organisationId;
        }
        if ($role !== '') {
            $user['role'] = $role;
            $user['membership_role'] = $role;
        }

        Session::set('user', $user);
        Session::remove('pending_invite_token');
        Session::flash('success', 'Invitation accepted. Complete your profile to finish joining the organisation.');
        return true;
    }

    public function login(): void
    {
        Middleware::guest();

        if ($this->demoSsoEnabled()) {
            $inviteToken = trim((string) ($_GET['invite'] ?? Session::get('pending_invite_token') ?? ''));
            if ($inviteToken !== '') {
                Session::set('pending_invite_token', $inviteToken);
            }
            View::render('auth/login', [
                'inviteToken' => $inviteToken,
                'demoEmail' => (string) ($_ENV['AUTH_BYPASS_USER_EMAIL'] ?? 'nomsa.khumalo@khumaloforensics.co.za'),
            ], 'public');
            return;
        }

        if ($this->dbLoginEnabled()) {
            $inviteToken = trim((string) ($_GET['invite'] ?? Session::get('pending_invite_token') ?? ''));
            if ($inviteToken !== '') {
                Session::set('pending_invite_token', $inviteToken);
            }
            View::render('auth/login', [
                'inviteToken' => $inviteToken,
                'dbLoginEnabled' => true,
                'dbLoginEmail' => (string) ($_ENV['AUTH_BYPASS_USER_EMAIL'] ?? 'nomsa.khumalo@khumaloforensics.co.za'),
            ], 'public');
            return;
        }

        if (Middleware::isAuthBypassed()) {
            $inviteToken = trim((string) ($_GET['invite'] ?? Session::get('pending_invite_token') ?? ''));
            if ($inviteToken !== '') {
                Session::set('pending_invite_token', $inviteToken);
                header('Location: /invite/' . rawurlencode($inviteToken));
                exit;
            }
            header('Location: /dashboard');
            exit;
        }

        $auth     = new Auth();
        $loginUrl = $auth->getLoginUrl();
        header('Location: ' . $loginUrl);
        exit;
    }

    public function dbLogin(): void
    {
        Middleware::csrfCheck();
        if (!$this->dbLoginEnabled()) {
            Session::flash('error', 'DB login is not enabled.');
            header('Location: /auth/login');
            exit;
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Enter a valid user email.');
            header('Location: /auth/login');
            exit;
        }

        try {
            $userService = new UserService();
            $user = $userService->findByEmailForDbLogin($email);
            if (!$user || empty($user['id'])) {
                Session::flash('error', 'No active DB user was found for that email.');
                header('Location: /auth/login');
                exit;
            }

            $profile = $userService->getProfileForDbLogin((string) $user['id']);
            if ($profile) {
                $user = array_merge($user, $profile, ['id' => (string) ($profile['user_id'] ?? $user['id'])]);
            }

            $user = $this->normalizeDemoUser($user, $email);
            Session::set('user', $user);
            Session::set('access_token', 'db-login-test-session');
            Session::remove('demo_sso_user');

            if ($this->acceptPendingInvite($user)) {
                header('Location: /profile/complete');
                exit;
            }

            Session::flash('success', 'Signed in as DB user ' . $email . '.');
            header('Location: /dashboard');
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'DB login failed: ' . $e->getMessage());
            header('Location: /auth/login');
            exit;
        }
    }

    public function demoLogin(): void
    {
        Middleware::csrfCheck();
        if (!$this->demoSsoEnabled()) {
            Session::flash('error', 'Demo SSO is not enabled.');
            header('Location: /auth/login');
            exit;
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Enter a valid demo SSO email.');
            header('Location: /auth/login');
            exit;
        }

        try {
            $userService = new UserService();
            $user = $userService->findByEmail($email);
            if (!$user || empty($user['id'])) {
                Session::flash('error', 'No local user exists for that SSO email. Register the demo account first.');
                header('Location: /auth/login');
                exit;
            }

            $profile = $userService->getProfile((string) $user['id']);
            if ($profile) {
                $user = array_merge($user, $profile, ['id' => (string) ($profile['user_id'] ?? $user['id'])]);
            }

            $user = $this->normalizeDemoUser($user, $email);
            $this->setDemoUserSession($user);

            if ($this->acceptPendingInvite($user)) {
                header('Location: /profile/complete');
                exit;
            }

            Session::flash('success', 'Signed in with demo SSO.');
            header('Location: /dashboard');
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Demo SSO login failed: ' . $e->getMessage());
            header('Location: /auth/login');
            exit;
        }
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

            if ($this->acceptPendingInvite(array_merge($user, ['keycloak_info' => $userInfo]))) {
                header('Location: /profile/complete');
                exit;
            }

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
        $auth = new Auth();
        $auth->clearSession();
        Session::destroy();
        header('Location: /');
        exit;
    }

    public function register(): void
    {
        Middleware::guest();

        $inviteToken = trim((string) ($_GET['invite'] ?? Session::get('pending_invite_token') ?? ''));
        if ($inviteToken !== '') {
            Session::set('pending_invite_token', $inviteToken);
        }

        $invite = false;
        $invitedEmail = '';
        $inviteOrganisationName = '';
        if ($inviteToken !== '') {
            $invite = (new OrganisationInvitationService())->findByToken($inviteToken);
            if (is_array($invite)) {
                $invitedEmail = strtolower((string) ($invite['invited_email'] ?? $invite['email'] ?? ''));
                $snapshot = $invite['organisation_snapshot'] ?? [];
                if (is_string($snapshot)) {
                    $decoded = json_decode($snapshot, true);
                    $snapshot = is_array($decoded) ? $decoded : [];
                }
                $org = is_array($snapshot['organisation'] ?? null) ? $snapshot['organisation'] : [];
                $inviteOrganisationName = (string) ($org['name'] ?? $invite['organisation_name'] ?? '');
            }
        }

        View::render('auth/register', [
            'demoSsoEnabled' => $this->demoSsoEnabled(),
            'inviteToken' => $inviteToken,
            'invitedEmail' => $invitedEmail,
            'inviteOrganisationName' => $inviteOrganisationName,
            'inviteIsActive' => is_array($invite) && strtolower((string) ($invite['status'] ?? '')) === 'pending',
        ], 'public');
    }

    public function demoRegister(): void
    {
        Middleware::csrfCheck();
        if (!$this->demoSsoEnabled()) {
            Session::flash('error', 'Demo SSO is not enabled.');
            header('Location: /auth/register');
            exit;
        }

        $firstName   = trim((string) ($_POST['first_name'] ?? ''));
        $lastName    = trim((string) ($_POST['last_name'] ?? ''));
        $inviteTokenPost = trim((string) ($_POST['invite'] ?? ''));
        if ($inviteTokenPost !== '') {
            Session::set('pending_invite_token', $inviteTokenPost);
        }

        $email       = strtolower(trim((string) ($_POST['email'] ?? '')));
        $orgName     = trim((string) ($_POST['organisation_name'] ?? ''));
        $phone       = trim((string) ($_POST['phone'] ?? ''));
        $country     = trim((string) ($_POST['country'] ?? 'ZA'));
        $accountType = trim((string) ($_POST['account_type'] ?? 'company'));

        $pendingInviteToken = trim((string) (Session::get('pending_invite_token') ?? ''));
        $pendingInvite = $pendingInviteToken !== ''
            ? (new OrganisationInvitationService())->findByToken($pendingInviteToken)
            : false;
        $inviteRegistration = is_array($pendingInvite)
            && strtolower((string) ($pendingInvite['status'] ?? '')) === 'pending';

        if ($firstName === '' || $lastName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please fill in all required demo SSO fields.');
            header('Location: /auth/register' . ($pendingInviteToken !== '' ? '?invite=' . rawurlencode($pendingInviteToken) : ''));
            exit;
        }

        if (!$inviteRegistration && $orgName === '') {
            Session::flash('error', 'Please fill in all required demo SSO fields.');
            header('Location: /auth/register');
            exit;
        }

        try {
            $orgService = new OrganisationService();
            $userService = new UserService();
            $tokenService = new TokenService();

            $existing = $userService->findByEmail($email);
            if ($existing && !empty($existing['id'])) {
                $profile = $userService->getProfile((string) $existing['id']);
                $user = $this->normalizeDemoUser($profile ? array_merge($existing, $profile) : $existing, $email);
                $this->setDemoUserSession($user);
                if ($pendingInvite !== false && $this->acceptPendingInvite($user)) {
                    header('Location: /profile/complete');
                    exit;
                }
                Session::flash('success', 'Existing demo SSO account signed in.');
                header('Location: /dashboard');
                exit;
            }

            if (is_array($pendingInvite) && strtolower((string) ($pendingInvite['status'] ?? '')) === 'pending') {
                $invitedEmail = strtolower((string) ($pendingInvite['invited_email'] ?? $pendingInvite['email'] ?? ''));
                if ($invitedEmail !== '' && $email !== $invitedEmail) {
                    Session::flash('error', 'Register with the invited email address: ' . $invitedEmail);
                    header('Location: /auth/register?invite=' . rawurlencode($pendingInviteToken));
                    exit;
                }

                $inviteOrgId = (string) ($pendingInvite['organisation_id'] ?? '');
                if ($inviteOrgId === '') {
                    throw new \RuntimeException('Invitation is missing organisation_id.');
                }

                $subject = 'demo-' . sha1($email);
                $userId = $userService->create([
                    'sso_provider' => 'keycloak_demo',
                    'sso_subject_id' => $subject,
                    'keycloak_id' => $subject,
                    'organisation_id' => $inviteOrgId,
                    'email' => $email,
                    'username' => $email,
                    'email_verified' => true,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone_number' => $phone,
                    'display_name' => trim($firstName . ' ' . $lastName),
                    'role' => 'viewer',
                    'status' => 'active',
                ]);

                if ($userId === '') {
                    throw new \RuntimeException('user-api did not return a user id. Check that user-api is running and the email is allowed.');
                }

                $membershipRole = strtolower((string) ($pendingInvite['invited_role'] ?? 'viewer'));
                if ($membershipRole === 'member' || $membershipRole === 'user') {
                    $membershipRole = 'viewer';
                }

                $user = [
                    'id' => $userId,
                    'user_id' => $userId,
                    'organisation_id' => $inviteOrgId,
                    'email' => $email,
                    'email_verified' => true,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone_number' => $phone,
                    'role' => $membershipRole,
                    'membership_role' => $membershipRole,
                    'sso_provider' => 'keycloak_demo',
                    'sso_subject_id' => $subject,
                    'keycloak_id' => $subject,
                ];
                $this->setDemoUserSession($user);

                if ($this->acceptPendingInvite($user)) {
                    header('Location: /profile/complete');
                    exit;
                }

                Session::flash('error', 'Account created, but the invitation could not be accepted.');
                header('Location: /invite/' . rawurlencode($pendingInviteToken));
                exit;
            }

            $orgId = $orgService->createForDemoRegistration([
                'name' => $orgName,
                'account_type' => $accountType,
                'country' => $country !== '' ? $country : 'ZA',
                'currency' => 'ZAR',
                'status' => 'active',
            ]);

            if ($orgId === '') {
                $detail = $orgService->lastError();
                throw new \RuntimeException('user-api did not return an organisation id' . ($detail !== '' ? ': ' . $detail : '.'));
            }

            $subject = 'demo-' . sha1($email);
            $userId = $userService->create([
                'sso_provider' => 'keycloak_demo',
                'sso_subject_id' => $subject,
                'keycloak_id' => $subject,
                'organisation_id' => $orgId,
                'email' => $email,
                'username' => $email,
                'email_verified' => true,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => $phone,
                'display_name' => trim($firstName . ' ' . $lastName),
                'role' => 'owner',
                'status' => 'active',
            ]);

            if ($userId === '') {
                throw new \RuntimeException('user-api did not return a user id.');
            }

            try {
                $tokenService->getOrCreateAccount($orgId);
            } catch (\Throwable $e) {
                // The UI can still continue; credits are service-owned and may be seeded separately.
            }

            $user = [
                'id' => $userId,
                'user_id' => $userId,
                'organisation_id' => $orgId,
                'email' => $email,
                'email_verified' => true,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => $phone,
                'role' => 'owner',
                'membership_role' => 'owner',
                'sso_provider' => 'keycloak_demo',
                'sso_subject_id' => $subject,
                'keycloak_id' => $subject,
            ];
            $this->setDemoUserSession($user);

            if ($this->acceptPendingInvite($user)) {
                header('Location: /profile/complete');
                exit;
            }

            Session::flash('success', 'Demo SSO account created and signed in.');
            header('Location: /dashboard');
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Demo SSO registration failed: ' . $e->getMessage());
            header('Location: /auth/register');
            exit;
        }
    }

    public function registerPost(): void
    {
        Middleware::guest();
        Middleware::csrfCheck();

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
            $tokenService = new TokenService();

            $orgId = $orgService->create([
                'name'         => $orgName,
                'account_type' => $accountType,
                'country'      => $country !== '' ? $country : 'ZA',
            ]);

            $userService->create([
                'keycloak_id'      => 'pending-' . uniqid(),
                'organisation_id'  => $orgId,
                'email'            => $email,
                'username'         => $email,
                'sso_provider'     => 'pending',
                'email_verified'   => false,
                'first_name'       => $firstName,
                'last_name'        => $lastName,
                'phone_number'     => $phone,
                'role'             => 'admin',
                'status'           => 'invited',
            ]);

            $tokenService->getOrCreateAccount($orgId);

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
