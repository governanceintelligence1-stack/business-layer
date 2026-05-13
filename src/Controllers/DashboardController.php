<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\CreditService;
use GI\Services\SubscriptionService;
use GI\Services\ApiKeyService;
use GI\Services\PlanService;
use GI\Services\ArticleService;

class DashboardController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $creditBalance = 0.0;
        $activePlan    = null;
        $apiKeys       = [];
        $transactions  = [];
        $products      = [];
        $recentArticles = [];
        $creditUsageTrend = [];
        $creditUsageTrendCaption = '';

        if (!empty($orgId)) {
            try {
                $creditService       = new CreditService();
                $subscriptionService = new SubscriptionService();
                $apiKeyService       = new ApiKeyService();
                $planService         = new PlanService();
                $creditBalance = $creditService->getBalance($orgId);
                $activePlan    = $subscriptionService->getCurrentPlan($orgId);
                $apiKeys       = $apiKeyService->getForOrganisation($orgId);
                $transactions  = $creditService->getTransactionHistory($orgId, 10);
                if ($activePlan && !empty($activePlan['plan_id'])) {
                    $products = $planService->getPlanProducts((string) $activePlan['plan_id']);
                }
            } catch (\Exception $e) {
                // Database/driver may be unavailable in local UI-only setups.
            }

            try {
                $trend = (new CreditService())->getUsageTrendLastDays($orgId, 7);
                $creditUsageTrend = $trend['points'];
                $creditUsageTrendCaption = $trend['caption'];
            } catch (\Throwable $e) {
                $trend = (new CreditService())->getUsageTrendLastDays('', 7);
                $creditUsageTrend = $trend['points'];
                $creditUsageTrendCaption = 'Credit trend unavailable.';
            }
        } else {
            $trend = (new CreditService())->getUsageTrendLastDays('', 7);
            $creditUsageTrend = $trend['points'];
            $creditUsageTrendCaption = $trend['caption'];
        }

        try {
            $recentArticles = (new ArticleService())->getPublishedRecent(4);
        } catch (\Throwable $e) {
            $recentArticles = [];
        }

        View::render('dashboard/index', [
            'user'          => $user,
            'creditBalance' => $creditBalance,
            'activePlan'    => $activePlan,
            'apiKeyCount'   => count($apiKeys),
            'transactions'  => $transactions,
            'products'      => $products,
            'recentArticles' => $recentArticles,
            'creditUsageTrend' => $creditUsageTrend,
            'creditUsageTrendCaption' => $creditUsageTrendCaption,
        ]);
    }
}
