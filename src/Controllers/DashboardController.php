<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\TokenService;
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

        $tokenBalance    = 0.0;
        $tokenReserved   = 0.0;
        $tokenAvailable  = 0.0;
        $activePlan    = null;
        $apiKeys       = [];
        $transactions  = [];
        $products      = [];
        $recentArticles = [];
        $tokenUsageTrend = [];
        $tokenUsageTrendCaption = '';
        $tokenUsageTrendSeries = [];
        $tokenUsageTrendDays = 7;

        if (!empty($orgId)) {
            try {
                $tokenService        = new TokenService();
                $subscriptionService = new SubscriptionService();
                $apiKeyService       = new ApiKeyService();
                $planService         = new PlanService();
                $snapshot       = $tokenService->getAccountSnapshot($orgId);
                $tokenBalance   = $snapshot['balance'];
                $tokenReserved  = $snapshot['reserved'];
                $tokenAvailable = $snapshot['available'];
                $activePlan    = $subscriptionService->getCurrentPlan($orgId);
                $apiKeys       = $apiKeyService->getForOrganisation($orgId);
                $transactions  = $tokenService->getRecentUsageTransactions($orgId, 10);
                $products = $planService->getPlatformProducts();
            } catch (\Exception $e) {
                // Database/driver may be unavailable in local UI-only setups.
            }

            try {
                $tokenService = new TokenService();
                $tokenUsageTrendSeries = $tokenService->getUsageTrendSeries($orgId, [7, 14, 30]);
                $trend = $tokenUsageTrendSeries[(string) $tokenUsageTrendDays] ?? ['points' => [], 'caption' => ''];
                $tokenUsageTrend = $trend['points'];
                $tokenUsageTrendCaption = $trend['caption'];
            } catch (\Throwable $e) {
                $tokenUsageTrendCaption = 'Token trend unavailable.';
            }
        }

        try {
            $recentArticles = (new ArticleService())->getPublishedRecent(4);
        } catch (\Throwable $e) {
            $recentArticles = [];
        }

        View::render('dashboard/index', [
            'user'          => $user,
            'tokenBalance'   => $tokenBalance,
            'tokenReserved'  => $tokenReserved,
            'tokenAvailable' => $tokenAvailable,
            'creditBalance'  => $tokenBalance,
            'activePlan'    => $activePlan,
            'apiKeyCount'   => count($apiKeys),
            'transactions'  => $transactions,
            'products'      => $products,
            'recentArticles' => $recentArticles,
            'tokenUsageTrend' => $tokenUsageTrend,
            'tokenUsageTrendCaption' => $tokenUsageTrendCaption,
            'tokenUsageTrendSeries' => $tokenUsageTrendSeries,
            'tokenUsageTrendDays' => $tokenUsageTrendDays,
            'creditUsageTrend' => $tokenUsageTrend,
            'creditUsageTrendCaption' => $tokenUsageTrendCaption,
        ]);
    }
}
