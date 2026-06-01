<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\BillingService;
use GI\Services\SubscriptionService;
use GI\Services\PaymentMethodService;

class BillingController
{
    private function invoiceTotal(array $invoice): float
    {
        return (float)($invoice['amount_total'] ?? $invoice['total'] ?? 0);
    }

    private function invoiceLineTotal(array $item): float
    {
        if (isset($item['line_total'])) {
            return (float)$item['line_total'];
        }
        if (isset($item['total'])) {
            return (float)$item['total'];
        }

        return (float)($item['quantity'] ?? 1) * (float)($item['unit_price'] ?? 0);
    }

    private function pdfEscape(string $text): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            $text
        );
    }

    private function buildPdf(array $commands): string
    {
        $content = implode("\n", $commands) . "\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R >> >> /Contents 7 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
            "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }

    private function buildInvoicePdf(array $invoice, array $user): string
    {
        $commands = [];
        $y = 792;
        $add = function (string $text, int $size = 10, string $font = 'F1', int $indent = 0) use (&$commands, &$y): void {
            if ($y < 48) {
                return;
            }
            $commands[] = sprintf(
                'BT /%s %d Tf %d %d Td (%s) Tj ET',
                $font,
                $size,
                50 + $indent,
                $y,
                $this->pdfEscape($text)
            );
            $y -= $size + 7;
        };

        $invoiceNumber = (string)($invoice['invoice_number'] ?? 'Invoice');
        $status = ucfirst((string)($invoice['status'] ?? 'issued'));
        $issuedAt = substr((string)($invoice['issued_at'] ?? $invoice['created_at'] ?? ''), 0, 10);
        $dueDate = substr((string)($invoice['due_date'] ?? ''), 0, 10);
        $customerName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        $customerEmail = (string)($user['email'] ?? '');

        $add('Governance Intelligence', 18, 'F2');
        $add('Invoice ' . $invoiceNumber, 16, 'F2');
        $add('Status: ' . $status);
        $add('Issued: ' . ($issuedAt !== '' ? $issuedAt : 'N/A') . '    Due: ' . ($dueDate !== '' ? $dueDate : 'N/A'));
        $y -= 8;

        $add('Bill To', 12, 'F2');
        if ($customerName !== '') {
            $add($customerName);
        }
        if ($customerEmail !== '') {
            $add($customerEmail);
        }
        $add('Organisation: ' . (string)($invoice['organisation_id'] ?? ''));
        $y -= 8;

        $add('Line Items', 12, 'F2');
        $add(sprintf('%-38s %8s %13s %13s', 'Description', 'Qty', 'Unit', 'Total'), 9, 'F3');
        $add(str_repeat('-', 78), 9, 'F3');

        foreach (($invoice['line_items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = (string)($item['description'] ?? 'Line item');
            if (strlen($description) > 38) {
                $description = substr($description, 0, 35) . '...';
            }
            $add(sprintf(
                '%-38s %8s %13s %13s',
                $description,
                (string)($item['quantity'] ?? '1'),
                'ZAR ' . number_format((float)($item['unit_price'] ?? 0), 2, '.', ''),
                'ZAR ' . number_format($this->invoiceLineTotal($item), 2, '.', '')
            ), 9, 'F3');
        }

        $y -= 8;
        $add('Subtotal: ZAR ' . number_format((float)($invoice['subtotal'] ?? $this->invoiceTotal($invoice)), 2, '.', ''), 10, 'F2', 270);
        $add('Tax: ZAR ' . number_format((float)($invoice['tax_amount'] ?? 0), 2, '.', ''), 10, 'F2', 270);
        $add('Total: ZAR ' . number_format($this->invoiceTotal($invoice), 2, '.', ''), 11, 'F2', 270);
        $add('Paid: ZAR ' . number_format((float)($invoice['amount_paid'] ?? 0), 2, '.', ''), 10, 'F2', 270);
        $add('Due: ZAR ' . number_format((float)($invoice['amount_due'] ?? 0), 2, '.', ''), 10, 'F2', 270);

        $y = 52;
        $add('Generated on ' . date('Y-m-d H:i') . '.', 8, 'F1');

        return $this->buildPdf($commands);
    }

    private function getAuthorisedInvoice(string $id, string $orgId, BillingService $billingService): array|false
    {
        if ($orgId === '') {
            return false;
        }

        $invoice = $billingService->getInvoice($id);
        if (!$invoice || (string)($invoice['organisation_id'] ?? '') !== $orgId) {
            return false;
        }

        return $invoice;
    }

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
            // Show a compact recent history on the Billing overview page.
            $invoices       = $orgId ? $billingService->getRecentInvoices($orgId, 5) : [];
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

        // Compute billing overview values: next invoice date, last payment amount, active plan
        $nextInvoiceDate = null;
        $lastPaymentAmount = 0.0;
        $activePlan = null;

        try {
            if ($orgId) {
                $subscriptionService = new SubscriptionService();
                $active = $subscriptionService->getActive($orgId);
                if ($active) {
                    $activePlan = $active['plan_name'] ?? ($active['plan_slug'] ?? null);
                    if (!empty($active['current_period_end'])) {
                        $ts = strtotime($active['current_period_end']);
                        if ($ts !== false) {
                            $nextInvoiceDate = date('F j, Y', $ts);
                        }
                    }
                }

                // Last payment: pick the most recent paid invoice from the recent list
                foreach ($invoices as $inv) {
                    $status = strtolower((string)($inv['status'] ?? ''));
                    $amt = (float)($inv['amount_paid'] ?? $inv['amount_total'] ?? $inv['total'] ?? 0);
                    if ($status === 'paid' || $amt > 0) {
                        $lastPaymentAmount = $amt;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore and let overview show placeholders
        }

        // Keep a reusable "existing billing method" for checkout preloading.
        Session::set('default_payment_method', $paymentMethod);

        View::render('billing/index', [
            'user'     => $user,
            'invoices' => $invoices,
            'paymentMethods' => $methods,
            'paymentMethod' => $paymentMethod,
            'nextInvoiceDate' => $nextInvoiceDate,
            'lastPaymentAmount' => $lastPaymentAmount,
            'activePlan' => $activePlan,
        ]);
    }

    public function history(): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $billingService = new BillingService();
        $result = $orgId ? $billingService->getInvoicesPaged($orgId, $perPage, $offset) : ['rows' => [], 'count' => 0];
        $invoices = $result['rows'];
        $total = (int)$result['count'];
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        View::render('billing/history', [
            'user' => $user,
            'invoices' => $invoices,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
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
                $shouldDefault,
                $digits
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
        $orgId = (string)($user['organisation_id'] ?? '');

        $billingService = new BillingService();
        $invoice        = $this->getAuthorisedInvoice($id, $orgId, $billingService);

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

    public function downloadInvoice(string $id): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $orgId = (string)($user['organisation_id'] ?? '');

        $billingService = new BillingService();
        $invoice = $this->getAuthorisedInvoice($id, $orgId, $billingService);

        if (!$invoice) {
            http_response_code(404);
            echo 'Invoice not found';
            return;
        }

        $filenameBase = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)($invoice['invoice_number'] ?? 'invoice'));
        $pdf = $this->buildInvoicePdf($invoice, is_array($user) ? $user : []);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
    }
}
