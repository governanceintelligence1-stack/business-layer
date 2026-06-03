<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\OrganisationInvitationService;
use GI\Services\UserService;

class InviteController
{
    public function show(string $token): void
    {
        $inviteService = new OrganisationInvitationService();
        $invite = $inviteService->findByToken($token);
        Session::set('pending_invite_token', $token);

        $user = Session::get('user') ?? null;
        View::render('invite/show', [
            'user' => $user,
            'invite' => $invite,
            'token' => $token,
            'isAuthenticated' => is_array($user) && !empty($user['email']),
        ], is_array($user) && !empty($user['email']) ? 'main' : 'public');
    }

    public function accept(string $token): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user = Session::get('user') ?? [];

        $accepted = (new OrganisationInvitationService())->accept($token, is_array($user) ? $user : []);
        if ($accepted === false) {
            Session::flash('error', 'Could not accept this invitation. Check that it has not expired or been cancelled.');
            header('Location: /invite/' . rawurlencode($token));
            exit;
        }

        $this->mergeAcceptedInviteIntoSession($accepted);
        Session::remove('pending_invite_token');
        Session::flash('success', 'Invitation accepted. Complete your profile to finish joining the organisation.');
        header('Location: /profile/complete');
        exit;
    }

    private function mergeAcceptedInviteIntoSession(array $accepted): void
    {
        $current = Session::get('user') ?? [];
        if (!is_array($current)) {
            $current = [];
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
            $current['organisation_id'] = $organisationId;
        }
        if ($role !== '') {
            $current['role'] = $role;
            $current['membership_role'] = $role;
        }

        Session::set('user', $current);
    }

    public function completeProfile(): void
    {
        Middleware::auth();
        $user = Session::get('user') ?? [];

        try {
            if (is_array($user) && !empty($user['id'])) {
                $profile = (new UserService())->getProfile((string) $user['id']);
                if ($profile) {
                    $user = array_merge($user, $profile);
                }
            }
        } catch (\Throwable $e) {
            // Keep session data if profile lookup is unavailable.
        }

        View::render('profile/complete', ['user' => $user]);
    }

    public function completeProfilePost(): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user = Session::get('user') ?? [];
        $userId = (string) ($user['id'] ?? $user['user_id'] ?? '');

        if ($userId === '') {
            Session::flash('error', 'Could not identify the logged-in user.');
            header('Location: /profile/complete');
            exit;
        }

        $payload = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'phone_number' => trim((string) ($_POST['phone_number'] ?? '')),
            'job_title' => trim((string) ($_POST['job_title'] ?? '')),
            'department' => trim((string) ($_POST['department'] ?? '')),
            'timezone' => trim((string) ($_POST['timezone'] ?? 'Africa/Johannesburg')),
            'locale' => trim((string) ($_POST['locale'] ?? 'en-ZA')),
        ];

        if ($payload['first_name'] === '' || $payload['last_name'] === '') {
            Session::flash('error', 'First name and last name are required.');
            header('Location: /profile/complete');
            exit;
        }

        $saved = (new UserService())->saveProfile($userId, $payload);
        $sessionUser = is_array($user) ? array_merge($user, $payload) : $payload;
        if (!isset($sessionUser['id']) && $userId !== '') {
            $sessionUser['id'] = $userId;
        }
        Session::set('user', $sessionUser);
        Session::flash($saved ? 'success' : 'error', $saved ? 'Profile completed.' : 'Profile could not be saved. Please try again.');
        header('Location: /dashboard');
        exit;
    }
}
