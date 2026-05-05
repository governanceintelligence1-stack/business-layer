<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\SubscriptionService;
use GI\Services\PlanService;

class SubscriptionController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $subService = new SubscriptionService();

        $currentSub = null;
        $allSubs    = [];

        if (!empty($orgId)) {
            try {
                $currentSub = $subService->getActive($orgId);
                $allSubs    = $subService->getForOrganisation($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('subscriptions/index', [
            'user'       => $user,
            'currentSub' => $currentSub,
            'allSubs'    => $allSubs,
        ]);
    }

    public function subscribe(string $planId): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        if (empty($orgId)) {
            Session::flash('error', 'No organisation found.');
            header('Location: /subscriptions');
            exit;
        }

        $planService = new PlanService();
        $plan        = $planService->findById($planId);

        if (!$plan) {
            Session::flash('error', 'Plan not found.');
            header('Location: /plans');
            exit;
        }

        $subService = new SubscriptionService();
        $existing   = $subService->getActive($orgId);

        if ($existing) {
            $subService->cancel($existing['id']);
        }

        $billing = $_POST['billing_cycle'] ?? 'monthly';
        $subService->create($orgId, $planId, $billing);

        Session::flash('success', "Subscribed to {$plan['name']} successfully.");
        header('Location: /subscriptions');
        exit;
    }

    public function cancel(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $subService = new SubscriptionService();
        $sub        = $subService->getActive($orgId ?? '');

        if ($sub) {
            $subService->cancel($sub['id']);
            Session::flash('success', 'Subscription cancelled.');
        }

        header('Location: /subscriptions');
        exit;
    }
}
