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
    private function detectCardBrand(string $digits): string
    {
        if (preg_match('/^4\d+$/', $digits)) {
            return 'Visa';
        }
        if (preg_match('/^(5[1-5]\d+|2(2[2-9]|[3-7]\d)\d+)$/', $digits)) {
            return 'Mastercard';
        }
        if (preg_match('/^3[47]\d+$/', $digits)) {
            return 'American Express';
        }
        if (preg_match('/^6(?:011|5\d{2})\d+$/', $digits)) {
            return 'Discover';
        }
        return 'Card';
    }

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
        $methods = [];

        try {
            $billingService = new BillingService();
            $invoices       = $orgId ? $billingService->getInvoices($orgId) : [];
            if ($orgId) {
                $pmService = new PaymentMethodService();
                $methods = $pmService->getForOrganisation((string)$orgId);
                foreach ($methods as &$method) {
                    $meta = [];
                    if (is_string($method['metadata'] ?? null) && ($method['metadata'] ?? '') !== '') {
                        $decoded = json_decode((string)$method['metadata'], true);
                        $meta = is_array($decoded) ? $decoded : [];
                    }
                    $method['cardholder_name'] = (string)($meta['cardholder_name'] ?? '');
                }
                unset($method);
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
            'paymentMethods' => $methods,
            'paymentMethod' => $paymentMethod,
        ]);
    }

    public function storePaymentMethod(): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $orgId = (string)($user['organisation_id'] ?? '');

        if ($orgId === '') {
            Session::flash('error', 'No organisation found for this account.');
            header('Location: /billing');
            exit;
        }

        $cardholderName = trim((string)($_POST['cardholder_name'] ?? ''));
        $cardNumberRaw = (string)($_POST['card_number'] ?? '');
        $expiryMonthRaw = trim((string)($_POST['expiry_month'] ?? ''));
        $expiryYearRaw = trim((string)($_POST['expiry_year'] ?? ''));
        $setDefault = isset($_POST['set_default']) && (string)$_POST['set_default'] === '1';

        $digits = preg_replace('/\D+/', '', $cardNumberRaw) ?? '';
        if ($cardholderName === '' || strlen($digits) < 12 || strlen($digits) > 19) {
            Session::flash('error', 'Please provide valid cardholder and card number details.');
            header('Location: /billing');
            exit;
        }

        $monthInt = (int)$expiryMonthRaw;
        $yearInt = (int)$expiryYearRaw;
        $currentYear = (int)date('Y');
        if ($monthInt < 1 || $monthInt > 12 || $yearInt < $currentYear || $yearInt > ($currentYear + 25)) {
            Session::flash('error', 'Please provide a valid expiry month and year.');
            header('Location: /billing');
            exit;
        }

        $last4 = substr($digits, -4);
        $brand = $this->detectCardBrand($digits);

        try {
            $service = new PaymentMethodService();
            $existing = $service->getForOrganisation($orgId);
            $shouldDefault = $setDefault || empty($existing);
            $service->saveCard(
                $orgId,
                (string)($user['id'] ?? ''),
                $brand,
                $last4,
                str_pad((string)$monthInt, 2, '0', STR_PAD_LEFT),
                (string)$yearInt,
                $cardholderName,
                $shouldDefault
            );

            Session::flash('success', 'Payment card added successfully.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not save payment method. Please try again.');
        }

        header('Location: /billing');
        exit;
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
