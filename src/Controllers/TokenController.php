<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\TokenService;

class TokenController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $summary              = ['balance' => 0.0, 'reserved' => 0.0, 'available' => 0.0];
        $transactions         = [];
        $pendingReservations  = [];
        $tokenService         = new TokenService();
        $monthUsage           = $tokenService->getTokensUsageThisCalendarMonth('');

        if (!empty($orgId)) {
            try {
                $summary             = $tokenService->getAccountSummary($orgId);
                $transactions        = $tokenService->getTransactionHistory($orgId, 100);
                $pendingReservations = $tokenService->getActiveReservations($orgId, 50);
                $monthUsage          = $tokenService->getTokensUsageThisCalendarMonth($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('tokens/index', [
            'user'                => $user,
            'balance'             => $summary['balance'],
            'reserved'            => $summary['reserved'],
            'available'           => $summary['available'],
            'pendingReservations' => $pendingReservations,
            'transactions'        => $transactions,
            'monthUsage'          => $monthUsage,
        ]);
    }

    public function history(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $tokenService = new TokenService();
        $transactions = [];
        $summary      = ['balance' => 0.0, 'reserved' => 0.0, 'available' => 0.0];
        $monthUsage   = $tokenService->getTokensUsageThisCalendarMonth('');

        if (!empty($orgId)) {
            try {
                $transactions = $tokenService->getTransactionHistory($orgId, 100);
                $summary      = $tokenService->getAccountSummary($orgId);
                $monthUsage   = $tokenService->getTokensUsageThisCalendarMonth($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('tokens/history', [
            'user'         => $user,
            'balance'      => $summary['balance'],
            'reserved'     => $summary['reserved'],
            'available'    => $summary['available'],
            'transactions' => $transactions,
            'monthUsage'   => $monthUsage,
        ]);
    }

    public function redirectFromCredits(): void
    {
        header('Location: /tokens', true, 301);
        exit;
    }

    public function redirectFromCreditsHistory(): void
    {
        header('Location: /tokens/history', true, 301);
        exit;
    }
}
