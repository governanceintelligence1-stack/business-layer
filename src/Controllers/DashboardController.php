<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\CreditService;
use GI\Services\SubscriptionService;
use GI\Services\ApiKeyService;

class DashboardController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $creditService       = new CreditService();
        $subscriptionService = new SubscriptionService();
        $apiKeyService       = new ApiKeyService();

        $creditBalance = 0.0;
        $activePlan    = null;
        $apiKeys       = [];
        $transactions  = [];

        if (!empty($orgId)) {
            try {
                $creditBalance = $creditService->getBalance($orgId);
                $activePlan    = $subscriptionService->getCurrentPlan($orgId);
                $apiKeys       = $apiKeyService->getForOrganisation($orgId);
                $transactions  = $creditService->getTransactionHistory($orgId, 10);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('dashboard/index', [
            'user'          => $user,
            'creditBalance' => $creditBalance,
            'activePlan'    => $activePlan,
            'apiKeyCount'   => count($apiKeys),
            'transactions'  => $transactions,
        ]);
    }
}
