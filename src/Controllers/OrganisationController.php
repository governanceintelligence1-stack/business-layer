<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\OrganisationService;
use GI\Services\OrganisationInvitationService;

class OrganisationController
{
    private function canManageMembers(array $user): bool
    {
        $role = strtolower((string) ($user['membership_role'] ?? $user['role'] ?? ''));
        return in_array($role, ['owner', 'admin'], true);
    }

    private function isOwnerMembership(string $membershipId, string $orgId): bool
    {
        if ($membershipId === '' || $orgId === '') {
            return false;
        }

        foreach ((new OrganisationService())->getMembers($orgId) as $member) {
            $id = (string) ($member['membership_id'] ?? $member['id'] ?? '');
            $role = strtolower((string) ($member['membership_role'] ?? $member['role'] ?? ''));
            if ($id === $membershipId) {
                return $role === 'owner';
            }
        }

        return false;
    }

    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $orgService = new OrganisationService();
        $org        = $orgId ? $orgService->findById($orgId) : null;
        $members    = $orgId ? $orgService->getMembers($orgId) : [];
        $invitations = $orgId ? (new OrganisationInvitationService())->getForOrganisation($orgId) : [];

        View::render('organisation/index', [
            'user'          => $user,
            'org'           => $org,
            'members'       => $members,
            'invitations'   => $invitations,
            'canManageTeam' => $this->canManageMembers(is_array($user) ? $user : []),
        ]);
    }

    public function update(): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        if (empty($orgId)) {
            Session::flash('error', 'No organisation found.');
            header('Location: /organisation');
            exit;
        }

        $orgService = new OrganisationService();
        $orgService->update($orgId, [
            'name'          => trim($_POST['name'] ?? ''),
            'billing_email' => trim($_POST['billing_email'] ?? ''),
            'tax_number'    => trim($_POST['tax_number'] ?? ''),
            'country'       => trim($_POST['country'] ?? ''),
        ]);

        Session::flash('success', 'Organisation updated successfully.');
        header('Location: /organisation');
        exit;
    }

    public function members(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $orgService = new OrganisationService();
        $members    = $orgId ? $orgService->getMembers($orgId) : [];
        $org        = $orgId ? $orgService->findById($orgId) : null;
        $invitations = $orgId ? (new OrganisationInvitationService())->getForOrganisation($orgId) : [];

        View::render('organisation/index', [
            'user'          => $user,
            'org'           => $org,
            'members'       => $members,
            'invitations'   => $invitations,
            'tab'           => 'members',
            'canManageTeam' => $this->canManageMembers(is_array($user) ? $user : []),
        ]);
    }

    public function invitations(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');

        $orgService = new OrganisationService();
        View::render('organisation/index', [
            'user'          => $user,
            'org'           => $orgId ? $orgService->findById($orgId) : null,
            'members'       => $orgId ? $orgService->getMembers($orgId) : [],
            'invitations'   => $orgId ? (new OrganisationInvitationService())->getForOrganisation($orgId) : [],
            'tab'           => 'invitations',
            'canManageTeam' => $this->canManageMembers(is_array($user) ? $user : []),
        ]);
    }

    public function invite(): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user  = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');

        if (!$this->canManageMembers(is_array($user) ? $user : [])) {
            Session::flash('error', 'Only owners and admins can invite members.');
            header('Location: /organisation/invitations');
            exit;
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? $_POST['invited_email'] ?? '')));
        $role  = strtolower(trim((string) ($_POST['role'] ?? $_POST['invited_role'] ?? 'viewer')));
        $allowedRoles = ['admin', 'billing_admin', 'developer', 'analyst', 'viewer', 'investigator'];

        if ($orgId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Enter a valid email address.');
            header('Location: /organisation/invitations');
            exit;
        }
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'viewer';
        }

        $invite = (new OrganisationInvitationService())->create(
            $orgId,
            $email,
            $role,
            (string) ($user['id'] ?? $user['user_id'] ?? '')
        );

        if ($invite === false) {
            Session::flash('error', 'Could not create invitation. Please try again.');
        } else {
            $link = $this->inviteLink((string) ($invite['invite_token'] ?? $invite['token'] ?? ''));
            Session::flash('success', $link !== ''
                ? 'Invitation created. Share this link: ' . $link
                : 'Invitation created.');
        }

        header('Location: /organisation/invitations');
        exit;
    }

    public function cancelInvite(string $inviteId): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user  = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');

        if (!$this->canManageMembers(is_array($user) ? $user : [])) {
            Session::flash('error', 'Only owners and admins can cancel invitations.');
            header('Location: /organisation/invitations');
            exit;
        }

        $cancelled = $orgId !== '' && (new OrganisationInvitationService())->cancel($orgId, $inviteId);
        Session::flash($cancelled ? 'success' : 'error', $cancelled ? 'Invitation cancelled.' : 'Could not cancel invitation.');
        header('Location: /organisation/invitations');
        exit;
    }

    public function updateMember(string $membershipId): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');
        if (!$this->canManageMembers(is_array($user) ? $user : [])) {
            Session::flash('error', 'Only owners and admins can update members.');
            header('Location: /organisation/members');
            exit;
        }
        if ($this->isOwnerMembership($membershipId, $orgId)) {
            Session::flash('error', 'Owner role cannot be changed.');
            header('Location: /organisation/members');
            exit;
        }

        $role = strtolower(trim((string) ($_POST['role'] ?? 'viewer')));
        $allowedRoles = ['admin', 'billing_admin', 'developer', 'analyst', 'viewer', 'investigator'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'viewer';
        }

        $updated = (new OrganisationService())->updateMember($membershipId, [
            'role' => $role,
        ]);
        Session::flash($updated ? 'success' : 'error', $updated ? 'Member updated.' : 'Could not update member.');
        header('Location: /organisation/members');
        exit;
    }

    public function updateMembers(): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');
        if (!$this->canManageMembers(is_array($user) ? $user : [])) {
            Session::flash('error', 'Only owners and admins can update members.');
            header('Location: /organisation/members');
            exit;
        }

        $roles = $_POST['roles'] ?? [];
        if (!is_array($roles) || $roles === []) {
            Session::flash('error', 'No role changes were submitted.');
            header('Location: /organisation/members');
            exit;
        }

        $allowedRoles = ['admin', 'billing_admin', 'developer', 'analyst', 'viewer', 'investigator'];
        $service = new OrganisationService();
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($roles as $membershipId => $role) {
            $membershipId = trim((string) $membershipId);
            $role = strtolower(trim((string) $role));
            if ($membershipId === '' || !in_array($role, $allowedRoles, true)) {
                $skipped++;
                continue;
            }
            if ($this->isOwnerMembership($membershipId, $orgId)) {
                $skipped++;
                continue;
            }
            if ($service->updateMember($membershipId, ['role' => $role])) {
                $updated++;
            } else {
                $failed++;
            }
        }

        if ($updated > 0) {
            Session::flash('success', $updated . ' member role(s) updated.');
        } elseif ($failed > 0) {
            Session::flash('error', 'Could not update member roles. Check that you are an organisation admin and try again.');
        } elseif ($skipped > 0 && $skipped === count($roles)) {
            Session::flash('error', 'No roles were changed (owner roles and invalid roles are skipped).');
        } else {
            Session::flash('error', 'No member roles were updated.');
        }
        header('Location: /organisation/members');
        exit;
    }

    public function removeMember(string $membershipId): void
    {
        Middleware::auth();
        Middleware::csrfCheck();
        $user = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');
        if (!$this->canManageMembers(is_array($user) ? $user : [])) {
            Session::flash('error', 'Only owners and admins can remove members.');
            header('Location: /organisation/members');
            exit;
        }
        if ($this->isOwnerMembership($membershipId, $orgId)) {
            Session::flash('error', 'Owner cannot be removed from the organisation.');
            header('Location: /organisation/members');
            exit;
        }

        $removed = (new OrganisationService())->removeMember($membershipId);
        Session::flash($removed ? 'success' : 'error', $removed ? 'Member removed.' : 'Could not remove member.');
        header('Location: /organisation/members');
        exit;
    }

    private function inviteLink(string $token): string
    {
        if ($token === '') {
            return '';
        }

        return rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/invite/' . rawurlencode($token);
    }
}
