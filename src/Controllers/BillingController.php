<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\BillingService;
use GI\Services\PaymentMethodService;

class BillingController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';
        $paymentMethod = [
            'brand' => 'Card',
            'last4' => '0000',
            'expiry' => '--/----',
            'cardholder_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        ];

        $invoices = [];

        try {
            $billingService = new BillingService();
            $invoices       = $orgId ? $billingService->getInvoices($orgId) : [];
            if ($orgId) {
                $pmService = new PaymentMethodService();
                $methods = $pmService->getForOrganisation($orgId);
                if (!empty($methods)) {
                    $default = $methods[0];
                    $paymentMethod = [
                        'brand' => $default['brand'] ?? 'Card',
                        'last4' => $default['last4'] ?? '0000',
                        'expiry' => ($default['expiry_month'] ?? '--') . '/' . ($default['expiry_year'] ?? '----'),
                        'cardholder_name' => $default['cardholder_name'] ?? $paymentMethod['cardholder_name'],
                    ];
                }
            }
        } catch (\Exception $e) {
            // Database may not be set up yet, continue with empty invoices
        }

        // Keep a reusable "existing billing method" for checkout preloading.
        Session::set('default_payment_method', $paymentMethod);

        View::render('billing/index', [
            'user'     => $user,
            'invoices' => $invoices,
            'paymentMethod' => $paymentMethod,
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
