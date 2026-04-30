<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\BillingService;

class BillingController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $billingService = new BillingService();
        $invoices       = [];

        try {
            $invoices = $orgId ? $billingService->getInvoices($orgId) : [];
        } catch (\Exception $e) {
            // Database may not be set up yet
        }

        View::render('billing/index', [
            'user'     => $user,
            'invoices' => $invoices,
        ]);
    }

    public function invoice(string $id): void
    {
        Middleware::auth();
        $user = Session::get('user');

        $billingService = new BillingService();
        $invoice        = $billingService->getInvoice($id);

        if (!$invoice) {
            http_response_code(404);
            echo '<h1>Invoice not found</h1>';
            return;
        }

        View::render('billing/index', [
            'user'    => $user,
            'invoice' => $invoice,
        ]);
    }
}
