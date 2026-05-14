<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\DB;
use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\BillingService;
use GI\Services\CreditService;
use GI\Services\PaymentMethodService;
use GI\Services\PaymentTransactionService;
use GI\Services\PayFastService;
use GI\Services\PlanService;
use GI\Services\SubscriptionService;
use InvalidArgumentException;

class CheckoutController
{
    private function paymentsStorageDir(): string
    {
        $dir = BASE_PATH . '/storage/payments';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function paymentFilePath(string $providerRef): string
    {
        return $this->paymentsStorageDir() . '/' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $providerRef) . '.json';
    }

    private function notifyLogPath(): string
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . '/payfast-notify.log';
    }

    private function generateManualPaymentId(): string
    {
        return 'PF-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function saveJsonPayment(array $payment): void
    {
        $providerRef = (string)($payment['provider_ref'] ?? '');
        if ($providerRef === '') {
            return;
        }
        $payment['updated_at'] = date('c');
        if (empty($payment['created_at'])) {
            $payment['created_at'] = date('c');
        }

        @file_put_contents(
            $this->paymentFilePath($providerRef),
            json_encode($payment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function getJsonPayment(string $providerRef): array
    {
        $path = $this->paymentFilePath($providerRef);
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function updateJsonPaymentStatus(string $providerRef, string $status, array $payload = []): void
    {
        if ($providerRef === '') {
            return;
        }
        $payment = $this->getJsonPayment($providerRef);
        if ($payment === []) {
            $payment = [
                'provider_ref' => $providerRef,
                'created_at' => date('c'),
            ];
        }
        $payment['status'] = $status;
        if (!empty($payload)) {
            $payment['last_payload'] = $payload;
        }
        $this->saveJsonPayment($payment);
    }

    private function findLatestJsonPaymentForUser(string $userId): array
    {
        if ($userId === '') {
            return [];
        }
        $files = glob($this->paymentsStorageDir() . '/*.json') ?: [];
        $latest = [];
        $latestTs = 0;
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false || $raw === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }
            if ((string)($decoded['user_id'] ?? '') !== $userId) {
                continue;
            }
            $ts = strtotime((string)($decoded['updated_at'] ?? $decoded['created_at'] ?? ''));
            $ts = $ts !== false ? $ts : 0;
            if ($ts >= $latestTs) {
                $latestTs = $ts;
                $latest = $decoded;
            }
        }
        return $latest;
    }

    private function appendNotifyLog(array $payload): void
    {
        $entry = [
            'at' => date('c'),
            'payload' => $payload,
        ];
        @file_put_contents(
            $this->notifyLogPath(),
            json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function isAuthBypassed(): bool
    {
        $value = strtolower(trim((string)($_ENV['AUTH_BYPASS'] ?? 'false')));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function resolveOrgIdForCheckout(array $user): string
    {
        $orgId = (string)($user['organisation_id'] ?? '');
        if ($orgId !== '') {
            return $orgId;
        }

        try {
            $row = DB::getInstance()->fetch('SELECT id FROM organisations ORDER BY created_at ASC LIMIT 1');
            if ($row && !empty($row['id'])) {
                return (string)$row['id'];
            }
        } catch (\Exception $e) {
            // Ignore and return empty.
        }

        if ($this->isAuthBypassed()) {
            return (string)($_ENV['DEV_BYPASS_ORG_ID'] ?? '00000000-0000-0000-0000-000000000001');
        }

        return '';
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

    private function resolveSelectedPaymentMethodId(array $user, string $orgId): ?string
    {
        $choice = trim((string)($_POST['payment_method_choice'] ?? ''));
        if ($choice === '' || $choice === 'new') {
            return null;
        }

        try {
            $pmService = new PaymentMethodService();
            $method = $pmService->findById($choice, $orgId);
            if ($method && !empty($method['id'])) {
                return (string)$method['id'];
            }
        } catch (\Throwable $e) {
            // Ignore and continue with null.
        }

        return null;
    }

    private function maybeSaveCardFromCheckout(array $user, string $orgId): ?string
    {
        $choice = trim((string)($_POST['payment_method_choice'] ?? ''));
        $shouldSave = isset($_POST['save_card']) && (string)$_POST['save_card'] === '1';
        if ($choice !== 'new' || !$shouldSave) {
            return null;
        }

        $cardholderName = trim((string)($_POST['cardholder_name'] ?? ''));
        $cardNumberRaw = (string)($_POST['card_number'] ?? '');
        $expiryMonthRaw = trim((string)($_POST['expiry_month'] ?? ''));
        $expiryYearRaw = trim((string)($_POST['expiry_year'] ?? ''));
        $digits = preg_replace('/\D+/', '', $cardNumberRaw) ?? '';
        if ($cardholderName === '' || strlen($digits) < 12 || strlen($digits) > 19) {
            return null;
        }

        $monthInt = (int)$expiryMonthRaw;
        $yearInt = (int)$expiryYearRaw;
        $currentYear = (int)date('Y');
        if ($monthInt < 1 || $monthInt > 12 || $yearInt < $currentYear || $yearInt > ($currentYear + 25)) {
            return null;
        }

        try {
            $pmService = new PaymentMethodService();
            return $pmService->saveCard(
                $orgId,
                (string)($user['id'] ?? ''),
                $this->detectCardBrand($digits),
                substr($digits, -4),
                str_pad((string)$monthInt, 2, '0', STR_PAD_LEFT),
                (string)$yearInt,
                $cardholderName,
                false
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveCheckoutPlan(string $planId): array
    {
        try {
            $planService = new PlanService();
            $plan = $planService->findById($planId);
            if ($plan) {
                $plan['_is_mock'] = false;
                return $plan;
            }
        } catch (\Exception $e) {
            // In local AUTH_BYPASS environments, DB drivers may be unavailable.
            // Fall back to mock plans so checkout can still reach PayFast.
        }

        $mockPlans = [
            'mock-starter' => ['id' => 'mock-starter', 'name' => 'Starter', 'price_monthly' => 450, 'credits_monthly' => 1000],
            'mock-pro' => ['id' => 'mock-pro', 'name' => 'Business Pro', 'price_monthly' => 1250, 'credits_monthly' => 5000],
            'mock-enterprise' => ['id' => 'mock-enterprise', 'name' => 'Enterprise', 'price_monthly' => 4500, 'credits_monthly' => 25000],
        ];

        $plan = $mockPlans[$planId] ?? $mockPlans['mock-pro'];
        $plan['_is_mock'] = true;
        return $plan;
    }

    public function index(): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';
        $planId = $_GET['plan_id'] ?? '';

        if (empty($planId)) {
            Session::flash('error', 'Please select a plan first.');
            header('Location: /plans');
            return;
        }

        try {
            $plan = $this->resolveCheckoutPlan($planId);
        } catch (\Exception $e) {
            $plan = [
                'id' => 'mock-pro',
                'name' => 'Business Pro',
                'price_monthly' => 1250,
                'credits_monthly' => 5000,
                '_is_mock' => true,
            ];
        }

        $paymentMethods = [];
        if (!empty($orgId)) {
            try {
                $paymentMethods = (new PaymentMethodService())->getForOrganisation((string)$orgId);
            } catch (\Exception $e) {
                $paymentMethods = [];
            }
        }

        View::render('checkout/index', [
            'user' => $user,
            'plan' => $plan,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function pay(): void
    {
        $dbgId = 'checkout-pay-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        error_log("[{$dbgId}] pay() start");
        Middleware::auth();
        $user = Session::get('user');
        $planId = $_POST['plan_id'] ?? '';
        $orgId = $this->resolveOrgIdForCheckout($user ?? []);
        error_log("[{$dbgId}] resolved context plan_id={$planId}, org_id=" . ($orgId !== '' ? $orgId : 'EMPTY'));

        $billingCycle = $_POST['billing_cycle'] ?? 'monthly';
        $planName = $_POST['plan_name'] ?? 'plan';
        $paymentMethodId = $this->resolveSelectedPaymentMethodId($user ?? [], $orgId)
            ?? $this->maybeSaveCardFromCheckout($user ?? [], $orgId);

        if (empty($planId)) {
            error_log("[{$dbgId}] stop: missing plan_id");
            Session::flash('error', 'Missing plan in checkout context.');
            header('Location: /plans');
            exit;
        }

        if (empty($orgId)) {
            error_log("[{$dbgId}] stop: missing org_id");
            Session::flash('error', 'No organisation is linked to your account yet. Please complete organisation setup before checkout.');
            header('Location: /checkout?plan_id=' . urlencode((string)$planId));
            exit;
        }

        $plan = $this->resolveCheckoutPlan($planId);
        error_log("[{$dbgId}] resolved plan id=" . ($plan['id'] ?? 'UNKNOWN') . ", name=" . ($plan['name'] ?? 'UNKNOWN'));

        $amount = (float) ($plan['price_monthly'] ?? 0);
        if ($billingCycle === 'annual') {
            $annual = (float) ($plan['price_annual'] ?? 0);
            $amount = $annual > 0 ? $annual : $amount * 12;
        }
        error_log("[{$dbgId}] billing_cycle={$billingCycle}, amount={$amount}");

        $persistedPlanId = (!empty($plan['_is_mock']) ? '' : (string)($plan['id'] ?? ''));
        error_log("[{$dbgId}] creating invoice + payment transaction persisted_plan_id=" . ($persistedPlanId !== '' ? $persistedPlanId : 'NULL'));

        $invoiceNumber = 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $merchantReference = 'PF-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $lineDescription = $planName . ' Plan - ' . ucfirst($billingCycle) . ' Subscription';
        $amountFormatted = number_format($amount, 2, '.', '');

        $pdo = DB::getInstance()->getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("\n                INSERT INTO billing_invoices (
                    organisation_id,
                    invoice_number,
                    status,
                    currency,
                    subtotal,
                    tax_amount,
                    total,
                    amount_paid,
                    amount_due,
                    due_date,
                    issued_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    :organisation_id,
                    :invoice_number,
                    'issued',
                    'ZAR',
                    :amount,
                    0,
                    :amount,
                    0,
                    :amount,
                    current_date + interval '7 days',
                    now(),
                    now(),
                    now()
                )
                RETURNING id
            ");
            $stmt->execute([
                'organisation_id' => $orgId,
                'invoice_number'  => $invoiceNumber,
                'amount'          => $amount,
            ]);
            $invoiceId = (string) $stmt->fetchColumn();
            if ($invoiceId === '') {
                throw new \RuntimeException('Failed to create billing invoice.');
            }

            $stmt = $pdo->prepare("\n                INSERT INTO billing_line_items (
                    invoice_id,
                    description,
                    quantity,
                    unit_price,
                    tax_rate,
                    line_total,
                    metadata
                )
                VALUES (
                    :invoice_id,
                    :description,
                    1,
                    :unit_price,
                    0,
                    :line_total,
                    :metadata::jsonb
                )
            ");
            $stmt->execute([
                'invoice_id'  => $invoiceId,
                'description' => $lineDescription,
                'unit_price'  => $amountFormatted,
                'line_total'  => $amountFormatted,
                'metadata'    => json_encode([
                    'plan_id' => $plan['id'] ?? null,
                    'plan_slug' => $plan['slug'] ?? null,
                    'billing_cycle' => $billingCycle,
                    'credits_monthly' => $plan['credits_monthly'] ?? null,
                ]),
            ]);

            $stmt = $pdo->prepare("\n                INSERT INTO payment_transactions (
                    organisation_id,
                    invoice_id,
                    payment_method_id,
                    provider,
                    merchant_reference,
                    idempotency_key,
                    amount,
                    currency,
                    status,
                    raw_response
                )
                VALUES (
                    :organisation_id,
                    :invoice_id,
                    :payment_method_id,
                    'payfast',
                    :merchant_reference,
                    :merchant_reference,
                    :amount,
                    'ZAR',
                    'initiated',
                    :raw_response::jsonb
                )
                RETURNING id
            ");
            $stmt->execute([
                'organisation_id'   => $orgId,
                'invoice_id'        => $invoiceId,
                'payment_method_id' => $paymentMethodId,
                'merchant_reference' => $merchantReference,
                'amount'            => $amountFormatted,
                'raw_response'      => json_encode([
                    'user_id'        => $user['id'] ?? null,
                    'plan_id'        => $plan['id'] ?? null,
                    'requested_plan_id' => $planId,
                    'billing_cycle'  => $billingCycle,
                    'payment_method_id' => $paymentMethodId,
                    'invoice_id'     => $invoiceId,
                ], JSON_UNESCAPED_SLASHES),
            ]);
            $paymentTransactionId = (string) $stmt->fetchColumn();
            if ($paymentTransactionId === '') {
                throw new \RuntimeException('Failed to create payment transaction.');
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->saveJsonPayment([
            'provider_ref' => $merchantReference,
            'status' => 'initiated',
            'provider' => 'payfast',
            'organisation_id' => $orgId,
            'user_id' => (string)($user['id'] ?? ''),
            'plan_id' => $persistedPlanId,
            'requested_plan_id' => $planId,
            'plan_name' => $planName,
            'billing_cycle' => $billingCycle,
            'amount' => $amountFormatted,
            'currency' => 'ZAR',
            'invoice_id' => $invoiceId,
            'payment_transaction_id' => $paymentTransactionId,
        ]);
        error_log("[{$dbgId}] checkout created invoice_id={$invoiceId} payment_transaction_id={$paymentTransactionId} merchant_reference={$merchantReference}");

        try {
            $payfast = PayFastService::fromEnv();
        } catch (InvalidArgumentException $e) {
            error_log("[{$dbgId}] PayFast not configured: " . $e->getMessage());
            Session::flash('error', 'PayFast merchant credentials are not configured. Set PAYFAST_MERCHANT_ID and PAYFAST_MERCHANT_KEY in the environment.');
            header('Location: /checkout?plan_id=' . urlencode((string) $planId));
            exit;
        }

        $paymentData = $payfast->buildPaymentData([
            'm_payment_id'   => $merchantReference,
            'amount'         => $amountFormatted,
            'item_name'      => $planName,
            'item_description' => 'Plan checkout for ' . $planName,
            'name_first'     => (string)($user['first_name'] ?? ''),
            'name_last'      => (string)($user['last_name'] ?? ''),
            'email_address'  => (string)($user['email'] ?? ''),
            'custom_str1'    => $orgId,
            'custom_str2'    => $persistedPlanId !== '' ? $persistedPlanId : $planId,
            'custom_str3'    => $billingCycle,
            'custom_str4'    => $invoiceId,
            'custom_str5'    => $paymentTransactionId,
        ]);
        error_log("[{$dbgId}] payfast payload m_payment_id={$merchantReference} custom_str4={$invoiceId} custom_str5={$paymentTransactionId}");
        error_log("[{$dbgId}] payfast payload prepared for merchant_id=" . ($paymentData['merchant_id'] ?? 'EMPTY'));

        $gatewayUrl = $payfast->getProcessUrl();
        error_log("[{$dbgId}] redirecting to gateway {$gatewayUrl}");
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting to PayFast</title></head><body>';
        echo '<p style="font-family:Arial,sans-serif;">Redirecting to secure payment...</p>';
        echo '<form id="pf_redirect" method="post" action="' . htmlspecialchars($gatewayUrl, ENT_QUOTES) . '">';
        foreach ($paymentData as $k => $v) {
            echo '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES) . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES) . '">';
        }
        echo '</form><script>document.getElementById("pf_redirect").submit();</script></body></html>';
        exit;
    }

    public function return(): void
    {
        $providerRef = trim((string) ($_GET['m_payment_id'] ?? $_GET['custom_str4'] ?? ''));
        $pfStatus = strtoupper(trim((string) ($_GET['payment_status'] ?? '')));
        $user = Session::get('user');

        if ($providerRef === '') {
            $fallback = $this->findLatestJsonPaymentForUser((string) ($user['id'] ?? ''));
            if (!empty($fallback['provider_ref'])) {
                $providerRef = (string) $fallback['provider_ref'];
            }
        }

        $tx = [];
        if ($providerRef !== '') {
            try {
                $txService = new PaymentTransactionService();
                $row = $txService->findByProviderRef($providerRef);
                if (is_array($row)) {
                    $tx = $row;
                }
            } catch (\Exception $e) {
                // Ignore DB failures during local dev.
            }
            if ($tx === []) {
                $tx = $this->getJsonPayment($providerRef);
            }
        }

        $dbStatus = $tx !== [] ? strtolower((string) ($tx['status'] ?? 'pending')) : '';
        $dbSuccessful = in_array($dbStatus, ['successful', 'paid', 'complete'], true);
        $dbFailed = $dbStatus === 'failed';
        $dbCancelled = $dbStatus === 'cancelled';

        $outcome = 'pending';
        $message = 'Payment received and pending confirmation. Please refresh shortly.';
        $status = $dbStatus !== '' ? $dbStatus : 'pending';

        if ($dbSuccessful) {
            $outcome = 'success';
            $message = 'Your payment is confirmed and your subscription is active.';
            $status = 'paid';
        } elseif (in_array($pfStatus, ['FAILED', 'FAILURE'], true)) {
            $outcome = 'failed';
            $message = 'PayFast reported this payment as failed. You can try again from Plans.';
            $status = 'failed';
        } elseif (in_array($pfStatus, ['CANCELLED', 'CANCELLED_BY_USER', 'CANCELLED_BY_MERCHANT'], true)) {
            $outcome = 'cancelled';
            $message = 'This payment was cancelled before completion.';
            $status = 'cancelled';
        } elseif ($pfStatus === 'COMPLETE') {
            $outcome = 'pending';
            $message = 'Your payment completed on PayFast. We are still confirming it — Billing will update in a moment.';
            $status = 'pending';
        } elseif ($pfStatus === 'PENDING') {
            $outcome = 'pending';
            $message = 'PayFast is still processing this payment. Check Billing shortly.';
            $status = 'pending';
        } elseif ($providerRef !== '' && $tx !== []) {
            if ($dbFailed) {
                $outcome = 'failed';
                $message = 'Payment did not complete successfully. You can retry from Plans.';
            } elseif ($dbCancelled) {
                $outcome = 'cancelled';
                $message = 'This payment was cancelled.';
            } else {
                $outcome = 'pending';
                $message = 'Payment received and pending confirmation. Please refresh shortly.';
            }
        } elseif ($providerRef !== '' && $tx === []) {
            $outcome = 'unknown';
            $message = 'Payment reference not found. Please contact support if this persists.';
            $status = 'unknown';
        } else {
            $outcome = 'pending';
            $message = 'Payment return received. Confirmation may still be processing.';
            $status = 'pending';
        }

        $pageTitle = match ($outcome) {
            'success' => 'Payment Successful',
            'failed' => 'Payment Failed',
            'cancelled' => 'Payment Cancelled',
            'unknown' => 'Payment Status',
            default => 'Payment Pending',
        };

        View::render('checkout/return', [
            'user' => $user,
            'outcome' => $outcome,
            'status' => $status,
            'message' => $message,
            'pageTitle' => $pageTitle,
            'providerRef' => $providerRef,
        ]);
    }

    public function cancel(): void
    {
        $user = Session::get('user');
        $providerRef = trim((string)($_GET['m_payment_id'] ?? ''));
        if ($providerRef !== '') {
            $this->updateJsonPaymentStatus($providerRef, 'cancelled', ['cancelled_by' => 'user']);
            try {
                $txService = new PaymentTransactionService();
                $tx = $txService->findByProviderRef($providerRef);
                if (is_array($tx) && !empty($tx['id']) && in_array($tx['status'] ?? '', ['initiated', 'pending'], true)) {
                    $txService->markCancelled((string)$tx['id'], ['cancelled_by' => 'user']);
                }
            } catch (\Exception $e) {
                // Ignore DB failures during local dev.
            }
        }

        View::render('checkout/cancel', [
            'user' => $user,
        ]);
    }

    public function notify(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $rawBody = (string) ($GLOBALS['GI_PAYFAST_RAW_POST'] ?? '');
        unset($GLOBALS['GI_PAYFAST_RAW_POST']);
        if ($rawBody === '') {
            $rawBody = file_get_contents('php://input') ?: '';
        }
        // First-line debug: record notify hits and raw body for visibility
        @file_put_contents(
            BASE_PATH . '/storage/payfast-notify-hit.log',
            date('c') . " notify hit\nRAW: " . $rawBody . "\n\n",
            FILE_APPEND
        );
        if ($rawBody === '' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST' && !empty($_POST)) {
            $rawBody = http_build_query($_POST);
        }

        file_put_contents(
            BASE_PATH . '/storage/payfast-itn.log',
            date('c') . "\n" . $rawBody . "\n\n",
            FILE_APPEND
        );

        $parsed = $this->parsePayFastRawPost($rawBody);
        if ($parsed === [] && !empty($_POST)) {
            foreach ($_POST as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }
                $parsed[$k] = is_scalar($v) || $v === null ? (string) $v : '';
            }
        }

        $this->appendNotifyLog($parsed);

        // Persist raw ITN to DB early so we always have a server-side record.
        $itnLogId = '';
        try {
            $itnLogId = DB::getInstance()->insert('payfast_itn_logs', [
                'received_at' => date('Y-m-d H:i:s'),
                'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'raw_body' => $rawBody,
                'parsed_payload' => json_encode($parsed, JSON_UNESCAPED_SLASHES),
                'merchant_reference' => trim((string) ($parsed['m_payment_id'] ?? '')),
                'pf_payment_id' => trim((string) ($parsed['pf_payment_id'] ?? '')),
                'payment_status' => trim((string) ($parsed['payment_status'] ?? '')),
                'amount_gross' => number_format((float) ($parsed['amount_gross'] ?? 0), 2, '.', ''),
                'signature_received' => trim((string) ($parsed['signature'] ?? '')),
            ]);
        } catch (\Throwable $e) {
            // Make ITN log failures visible for local debugging.
            error_log('checkout/notify: failed to insert payfast_itn_logs: ' . $e->getMessage());
            http_response_code(500);
            echo 'Failed to insert ITN log: ' . $e->getMessage();
            return;
        }

        $stringPayload = [];
        foreach ($parsed as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $stringPayload[$k] = $v === null ? '' : (string) $v;
        }

        $skipSignature = filter_var(
            getenv('PAYFAST_NOTIFY_SKIP_SIGNATURE') !== false
                ? getenv('PAYFAST_NOTIFY_SKIP_SIGNATURE')
                : ($_ENV['PAYFAST_NOTIFY_SKIP_SIGNATURE'] ?? ''),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$skipSignature) {
            $payfast = PayFastService::tryFromEnv();
            if ($payfast === null) {
                error_log('checkout/notify: PayFast merchant credentials missing');
                http_response_code(503);
                echo 'PayFast not configured';

                return;
            }

            // Prefer X-Forwarded-For when explicitly enabled in env (useful behind proxies).
            $useXff = filter_var((string) (getenv('PAYFAST_USE_X_FORWARDED_FOR') ?: ($_ENV['PAYFAST_USE_X_FORWARDED_FOR'] ?? '')), FILTER_VALIDATE_BOOLEAN);
            $remoteAddr = '0.0.0.0';
            if ($useXff && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // Use first IP in the list
                $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                $remoteAddr = trim($parts[0]);
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $remoteAddr = (string) $_SERVER['REMOTE_ADDR'];
            }
            $providerRefEarly = trim((string) ($stringPayload['m_payment_id'] ?? ''));
            $expectedAmount = null;
            if ($providerRefEarly !== '') {
                try {
                    $txEarly = (new PaymentTransactionService())->findByProviderRef($providerRefEarly);
                    if (is_array($txEarly) && array_key_exists('amount', $txEarly)) {
                        $expectedAmount = number_format((float) $txEarly['amount'], 2, '.', '');
                    }
                } catch (\Throwable $e) {
                    // amount check skipped
                }
            }

            $rawForSig = $rawBody !== '' ? $rawBody : null;
            if (!$payfast->validateItn($stringPayload, $remoteAddr, $expectedAmount, $rawForSig)) {
                // Update log with validation failure if possible.
                try {
                    if ($itnLogId !== '') {
                        DB::getInstance()->update('payfast_itn_logs', ['signature_valid' => false, 'processing_status' => 'validation_failed', 'processing_message' => 'signature or source invalid', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $itnLogId]);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                http_response_code(400);
                echo 'ITN validation failed';

                return;
            }
        }

        $providerRef = trim((string) ($parsed['m_payment_id'] ?? ''));
        $paymentStatus = strtolower((string) ($parsed['payment_status'] ?? ''));
        if ($providerRef !== '') {
            $status = $paymentStatus !== '' ? $paymentStatus : 'notified';
            $this->updateJsonPaymentStatus($providerRef, $status, $parsed);
        }

        if ($providerRef === '') {
            http_response_code(200);
            echo 'OK';

            return;
        }

        try {
            $code = $this->processPayfastItnInDatabase($stringPayload, $parsed);

            // Mark ITN log processed with the response code.
            try {
                if ($itnLogId !== '') {
                    DB::getInstance()->update('payfast_itn_logs', ['signature_valid' => true, 'processing_status' => ($code === 200 ? 'processed' : 'partial'), 'processing_message' => ($code === 200 ? '' : 'partial processing'), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $itnLogId]);
                }
            } catch (\Throwable $e) {
                // ignore
            }

            http_response_code($code);
            if ($code === 400) {
                echo 'Invalid amount';
            } else {
                echo 'OK';
            }
        } catch (\Throwable $e) {
            error_log('checkout/notify failed: ' . $e->getMessage());
            try {
                if ($itnLogId !== '') {
                    DB::getInstance()->update('payfast_itn_logs', ['processing_status' => 'error', 'processing_message' => substr($e->getMessage(), 0, 4000), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $itnLogId]);
                }
            } catch (\Throwable $e) {
                // ignore
            }
            http_response_code(500);
            echo 'ITN error';
        }
    }

    /**
     * Single DB transaction: validate amount, mark payment outcome, activate subscription, grant credits.
     * Credits and subscription changes must only run here (not on return_url).
     *
     * @param array<string, string> $stringPayload
     * @param array<string, string> $parsedPayload
     */
    private function processPayfastItnInDatabase(array $stringPayload, array $parsedPayload): int
    {
        $merchantRef = trim((string) ($parsedPayload['m_payment_id'] ?? ''));
        if ($merchantRef === '') {
            return 200;
        }

        $paymentStatus = strtolower((string) ($parsedPayload['payment_status'] ?? ''));

        $earlyTx = (new PaymentTransactionService())->findByProviderRef($merchantRef);
        if (is_array($earlyTx) && !empty($earlyTx['organisation_id'])) {
            (new CreditService())->getOrCreateAccount((string) $earlyTx['organisation_id']);
        }

        $pdo = DB::getInstance()->getPdo();
        $pdo->beginTransaction();
        $txSvc = new PaymentTransactionService();
        try {
            $stmt = $pdo->prepare("\n                SELECT
                    pt.id AS payment_transaction_id,
                    pt.invoice_id,
                    pt.organisation_id,
                    pt.amount,
                    pt.status,
                    pt.raw_response,
                    bi.total,
                    bi.credits_granted
                FROM payment_transactions pt
                JOIN billing_invoices bi ON bi.id = pt.invoice_id
                WHERE pt.merchant_reference = :merchant_reference
                FOR UPDATE\n            ");
            $stmt->execute(['merchant_reference' => $merchantRef]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || empty($row['payment_transaction_id'])) {
                error_log('checkout/notify: payment transaction not found for merchant_reference=' . $merchantRef);
                $pdo->rollBack();

                return 200;
            }
            error_log('checkout/notify: matched merchant_reference=' . $merchantRef . ' invoice_id=' . ($row['invoice_id'] ?? 'NULL'));

            // If invoice already had credits granted, avoid duplicate processing.
            if (!empty($row['credits_granted'])) {
                error_log('checkout/notify: invoice already credited for merchant_reference=' . $merchantRef);
                $pdo->commit();

                return 200;
            }

            // Idempotency: if we've already recorded an ITN with the same PayFast pf_payment_id,
            // don't process it again.
            $existingRaw = (string) ($row['raw_response'] ?? '');
            if ($existingRaw !== '') {
                $decoded = json_decode($existingRaw, true);
                if (is_array($decoded) && !empty($decoded['payfast_itn']['pf_payment_id'])) {
                    $existingPf = (string) $decoded['payfast_itn']['pf_payment_id'];
                    $incomingPf = trim((string) ($parsedPayload['pf_payment_id'] ?? ''));
                    if ($incomingPf !== '' && $existingPf === $incomingPf) {
                        error_log('checkout/notify: duplicate ITN ignored pf_payment_id=' . $incomingPf);
                        $pdo->commit();

                        return 200;
                    }
                }
            }

            if (($row['status'] ?? '') === 'successful') {
                $pdo->commit();

                return 200;
            }

            $expected = number_format((float) ($row['total'] ?? $row['amount'] ?? 0), 2, '.', '');
            $grossRaw = $stringPayload['amount_gross'] ?? $parsedPayload['amount_gross'] ?? '0';
            $received = number_format((float) $grossRaw, 2, '.', '');
            if ($received !== $expected) {
                $txSvc->markFailed((string) $row['payment_transaction_id'], $parsedPayload);
                $pdo->commit();

                return 400;
            }

            if (!in_array($paymentStatus, ['complete', 'completed'], true)) {
                if (in_array($paymentStatus, ['failed', 'failure', 'cancelled', 'canceled'], true)) {
                    $txSvc->markFailed((string) $row['payment_transaction_id'], $parsedPayload);
                }
                $pdo->commit();

                return 200;
            }

            $this->fulfillLockedPayfastItnRow($row, $stringPayload);
            $pdo->commit();
            error_log('checkout/notify: updated payment_transaction_id=' . ($row['payment_transaction_id'] ?? 'NULL') . ' and invoice_id=' . ($row['invoice_id'] ?? 'NULL'));

            return 200;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $lockedTx
     * @param array<string, string> $itnPayload
     */
    private function fulfillLockedPayfastItnRow(array $lockedTx, array $itnPayload): void
    {
        $txSvc = new PaymentTransactionService();
        $billingService = new BillingService();
        $subscriptionService = new SubscriptionService();
        $creditService = new CreditService();
        $planService = new PlanService();

        $orgId = (string) ($lockedTx['organisation_id'] ?? '');
        $payloadData = $this->decodeTxPayload($lockedTx['raw_response'] ?? ($lockedTx['raw_payload'] ?? null));
        $planId = (string) ($payloadData['plan_id'] ?? '');
        $inner = isset($payloadData['payload']) && is_array($payloadData['payload'])
            ? $payloadData['payload']
            : $payloadData;
        $itnPlan = trim((string) ($itnPayload['custom_str2'] ?? ''));

        if ($itnPlan !== '') {
            try {
                $candidatePlan = $planService->findById($itnPlan);
                if ($candidatePlan) {
                    $planId = $itnPlan;
                }
            } catch (\Throwable $e) {
                // Keep existing planId and allow fallback logic to continue.
            }
        }
        if ($planId === '') {
            $planId = $this->resolvePlanIdForMockPayment($inner + ['requested_plan_id' => $itnPlan]);
        }
        if ($orgId === '' || $planId === '') {
            throw new \RuntimeException('Missing organisation or plan for PayFast fulfillment.');
        }

        $billingCycle = strtolower(trim((string) ($itnPayload['custom_str3'] ?? ($inner['billing_cycle'] ?? 'monthly'))));
        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            $billingCycle = 'monthly';
        }

        $existing = $subscriptionService->getActive($orgId);
        if ($existing) {
            $subscriptionService->cancel((string) $existing['id']);
        }
        $subscriptionId = $subscriptionService->create($orgId, $planId, $billingCycle);

        $plan = $planService->findById($planId);
        $planName = (string) ($plan['name'] ?? 'Plan');
        $monthlyCredits = (float) ($plan['credits_monthly'] ?? 0);

        $invoiceId = trim((string) ($lockedTx['invoice_id'] ?? ''));
        if ($invoiceId === '') {
            $amount = (float) ($lockedTx['amount'] ?? 0);
            $invoiceId = $billingService->createInvoice($orgId, [[
                'description' => $planName . ' subscription',
                'quantity'    => 1,
                'unit_price'  => $amount,
                'tax_rate'    => 0,
                'line_total'  => $amount,
            ]]);
        }

        $billingService->markPaid($invoiceId);

        if ($monthlyCredits > 0) {
            $creditService->addCredits(
                $orgId,
                $monthlyCredits,
                'Plan activation credits',
                'subscription',
                $planId,
                null,
                true
            );
        }

        $billingService->markCreditsGrantedForInvoice($invoiceId, $monthlyCredits);

        // Attach subscription id to invoice when column exists, non-fatal if absent.
        try {
            DB::getInstance()->update('billing_invoices', array_filter(['subscription_id' => $subscriptionId]), ['id' => $invoiceId]);
        } catch (\Throwable $e) {
            // ignore
        }

        $txId = (string) ($lockedTx['payment_transaction_id'] ?? ($lockedTx['id'] ?? ''));
        try {
            // Perform the final update within the current transaction using the same PDO
            $pdo = DB::getInstance()->getPdo();
            $row = $pdo->prepare("SELECT raw_response FROM payment_transactions WHERE id = :id");
            $row->execute(['id' => $txId]);
            $existing = $row->fetch(\PDO::FETCH_ASSOC)['raw_response'] ?? '';
            $existingDecoded = [];
            if (is_string($existing) && $existing !== '') {
                $decoded = json_decode($existing, true);
                $existingDecoded = is_array($decoded) ? $decoded : [];
            }
            $merged = array_merge($existingDecoded, ['payfast_itn' => $itnPayload]);
            $pfId = trim((string)($itnPayload['pf_payment_id'] ?? '')) ?: null;

            $upd = $pdo->prepare("UPDATE payment_transactions SET status = :status, raw_response = :raw_response::jsonb, provider_transaction_id = :pf_id, invoice_id = :invoice_id, updated_at = now() WHERE id = :id");
            $upd->execute([
                'status' => 'successful',
                'raw_response' => json_encode($merged, JSON_UNESCAPED_SLASHES),
                'pf_id' => $pfId,
                'invoice_id' => $invoiceId,
                'id' => $txId,
            ]);

            $updatedRows = $upd->rowCount();
            error_log('checkout/notify: direct update updated_rows=' . intval($updatedRows) . ' txId=' . $txId);
            try {
                $after = DB::getInstance()->fetch('SELECT id,status,provider_transaction_id,raw_response FROM payment_transactions WHERE id = :id', ['id' => $txId]);
                error_log('checkout/notify: post-update tx status=' . ($after['status'] ?? 'NULL') . ' provider_tx=' . ($after['provider_transaction_id'] ?? 'NULL'));
            } catch (\Throwable $e) {
                error_log('checkout/notify: failed to fetch post-update tx: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            error_log('checkout/notify: direct update failed: ' . $e->getMessage());
            throw $e;
        }

        // Update subscription with last invoice/payment refs and insert events/audit where possible.
        try {
            DB::getInstance()->update('subscriptions', array_filter([
                'last_invoice_id' => $invoiceId,
                'last_payment_transaction_id' => $txId,
            ]), ['id' => $subscriptionId]);
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::getInstance()->insert('subscription_events', [
                'subscription_id' => $subscriptionId,
                'organisation_id' => $orgId,
                'event_type' => 'payment_successful',
                'metadata' => json_encode(['invoice_id' => $invoiceId, 'payment_transaction_id' => $txId], JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::getInstance()->insert('audit_logs', [
                'organisation_id' => $orgId,
                'action' => 'subscription.payment_successful',
                'entity_type' => 'subscription',
                'entity_id' => $subscriptionId,
                'new_values' => json_encode([
                    'subscription_id' => $subscriptionId,
                    'invoice_id' => $invoiceId,
                    'payment_transaction_id' => $txId,
                ], JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function decodeTxPayload(mixed $rawPayload): array
    {
        if (is_array($rawPayload)) {
            return $rawPayload;
        }
        if (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function parsePayFastRawPost(string $rawBody): array
    {
        $data = [];

        if ($rawBody === '') {
            return $data;
        }

        foreach (explode('&', $rawBody) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');

            $decodedKey = urldecode($key);
            $decodedValue = urldecode($value);

            if ($decodedKey !== '') {
                $data[$decodedKey] = $decodedValue;
            }
        }

        return $data;
    }

    private function resolvePlanIdForMockPayment(array $payload): string
    {
        $requested = strtolower((string)($payload['requested_plan_id'] ?? ''));
        $planName = strtolower((string)($payload['plan_name'] ?? ''));
        $candidates = [];
        if ($requested !== '') {
            $candidates[] = $requested;
        }
        if ($planName !== '') {
            $candidates[] = $planName;
        }

        $map = [
            'mock-starter' => ['starter'],
            'mock-pro' => ['business pro', 'pro'],
            'mock-enterprise' => ['enterprise'],
        ];
        foreach ($map as $key => $synonyms) {
            if ($requested === $key) {
                $candidates = array_merge($candidates, $synonyms);
                break;
            }
        }

        try {
            $planService = new PlanService();
            $activePlans = $planService->getActive();
            foreach ($activePlans as $plan) {
                $name = strtolower((string)($plan['name'] ?? ''));
                $slug = strtolower((string)($plan['slug'] ?? ''));
                foreach ($candidates as $needle) {
                    if ($needle !== '' && (str_contains($name, $needle) || str_contains($slug, $needle))) {
                        return (string)($plan['id'] ?? '');
                    }
                }
            }
            if (!empty($activePlans[0]['id'])) {
                return (string)$activePlans[0]['id'];
            }
        } catch (\Exception $e) {
            // Ignore and return empty
        }

        return '';
    }
}
