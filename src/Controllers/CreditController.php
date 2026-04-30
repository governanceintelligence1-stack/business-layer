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

        $creditService = new CreditService();

        $balance      = 0.0;
        $transactions = [];

        if (!empty($orgId)) {
            try {
                $balance      = $creditService->getBalance($orgId);
                $transactions = $creditService->getTransactionHistory($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('credits/index', [
            'user'         => $user,
            'balance'      => $balance,
            'transactions' => $transactions,
        ]);
    }

    public function topup(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $amount = (float) ($_POST['amount'] ?? 0);

        if ($amount <= 0 || empty($orgId)) {
            Session::flash('error', 'Invalid top-up amount.');
            header('Location: /credits');
            exit;
        }

        $creditService = new CreditService();
        $creditService->addCredits($orgId, $amount, 'Manual top-up', 'topup', '');

        Session::flash('success', number_format($amount, 0) . ' credits added successfully.');
        header('Location: /credits');
        exit;
    }

    public function history(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $creditService = new CreditService();
        $transactions  = [];
        $balance       = 0.0;

        if (!empty($orgId)) {
            try {
                $transactions = $creditService->getTransactionHistory($orgId, 100);
                $balance      = $creditService->getBalance($orgId);
            } catch (\Exception $e) {
                // Database may not be set up yet
            }
        }

        View::render('credits/index', [
            'user'         => $user,
            'balance'      => $balance,
            'transactions' => $transactions,
        ]);
    }
}
