<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\OrganisationService;

class OrganisationController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $orgService = new OrganisationService();
        $org        = $orgId ? $orgService->findById($orgId) : null;
        $members    = $orgId ? $orgService->getMembers($orgId) : [];

        View::render('organisation/index', [
            'user'    => $user,
            'org'     => $org,
            'members' => $members,
        ]);
    }

    public function update(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        if (empty($orgId)) {
            Session::flash('error', 'No organisation found.');
            header('Location: /organisation');
            exit;
        }

        $orgService = new OrganisationService();
        $orgService->update($orgId, [
            'name'    => trim($_POST['name'] ?? ''),
            'phone'   => trim($_POST['phone'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
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

        View::render('organisation/index', [
            'user'    => $user,
            'members' => $members,
            'tab'     => 'members',
        ]);
    }
}
