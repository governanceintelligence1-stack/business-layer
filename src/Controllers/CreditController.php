<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\CreditService;

class CreditController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $balance         = 0.0;
        $transactions    = [];
        $creditService   = new CreditService();
        $monthUsage      = $creditService->getCreditsUsageThisCalendarMonth('');

        if (!empty($orgId)) {
            try {
                $balance       = $creditService->getBalance($orgId);
                $transactions  = $creditService->getTransactionHistory($orgId, 100);
                $monthUsage    = $creditService->getCreditsUsageThisCalendarMonth($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('credits/index', [
            'user'          => $user,
            'balance'       => $balance,
            'transactions'  => $transactions,
            'monthUsage'    => $monthUsage,
        ]);
    }

    public function topup(): void
    {
        // Manual top-ups are disabled. Top-ups are only granted via subscriptions.
        Middleware::auth();
        Session::flash('error', 'Manual top-ups are disabled. Use a subscription to receive credits.');
        header('Location: /credits');
        exit;
    }

    public function history(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $creditService  = new CreditService();
        $transactions   = [];
        $balance        = 0.0;
        $monthUsage     = $creditService->getCreditsUsageThisCalendarMonth('');

        if (!empty($orgId)) {
            try {
                $transactions = $creditService->getTransactionHistory($orgId, 100);
                $balance      = $creditService->getBalance($orgId);
                $monthUsage   = $creditService->getCreditsUsageThisCalendarMonth($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('credits/history', [
            'user'          => $user,
            'balance'       => $balance,
            'transactions'  => $transactions,
            'monthUsage'    => $monthUsage,
        ]);
    }
}
