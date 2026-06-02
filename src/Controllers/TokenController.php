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

        $accountSnapshot     = ['balance' => 0.0, 'reserved' => 0.0, 'available' => 0.0];
        $pendingReservations = [];
        $transactions        = [];
        $tokenService        = new TokenService();
        $monthUsage          = $tokenService->getTokensUsageThisCalendarMonth('');

        if (!empty($orgId)) {
            try {
                $accountSnapshot     = $tokenService->getAccountSnapshot($orgId);
                $pendingReservations = $tokenService->getActiveReservations($orgId);
                $transactions        = $tokenService->getTransactionHistory($orgId, 100);
                $monthUsage          = $tokenService->getTokensUsageThisCalendarMonth($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('tokens/index', [
            'user'                => $user,
            'accountSnapshot'     => $accountSnapshot,
            'balance'             => $accountSnapshot['balance'],
            'reserved'            => $accountSnapshot['reserved'],
            'available'           => $accountSnapshot['available'],
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

        $tokenService        = new TokenService();
        $transactions        = [];
        $accountSnapshot     = ['balance' => 0.0, 'reserved' => 0.0, 'available' => 0.0];
        $pendingReservations = [];
        $monthUsage          = $tokenService->getTokensUsageThisCalendarMonth('');

        if (!empty($orgId)) {
            try {
                $accountSnapshot     = $tokenService->getAccountSnapshot($orgId);
                $pendingReservations = $tokenService->getActiveReservations($orgId);
                $transactions        = $tokenService->getTransactionHistory($orgId, 100);
                $monthUsage          = $tokenService->getTokensUsageThisCalendarMonth($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('tokens/history', [
            'user'                => $user,
            'accountSnapshot'     => $accountSnapshot,
            'balance'             => $accountSnapshot['balance'],
            'reserved'            => $accountSnapshot['reserved'],
            'available'           => $accountSnapshot['available'],
            'pendingReservations' => $pendingReservations,
            'transactions'        => $transactions,
            'monthUsage'          => $monthUsage,
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
