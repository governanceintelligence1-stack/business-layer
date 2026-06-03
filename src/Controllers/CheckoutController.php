<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\PaymentMethodService;
use GI\Services\PaymentTransactionService;
use GI\Services\PayFastService;
use GI\Services\PlanService;
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

    private function saveJsonPayment(array $payment): void
    {
        $providerRef = (string) ($payment['provider_ref'] ?? '');
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
        if ($payload !== []) {
            $payment['last_payload'] = $payload;
        }
        $this->saveJsonPayment($payment);
    }

    private function findLatestJsonPaymentForUser(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $latest = [];
        $latestTs = 0;
        foreach (glob($this->paymentsStorageDir() . '/*.json') ?: [] as $file) {
            $raw = @file_get_contents($file);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($decoded) || (string) ($decoded['user_id'] ?? '') !== $userId) {
                continue;
            }

            $ts = strtotime((string) ($decoded['updated_at'] ?? $decoded['created_at'] ?? ''));
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
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        @file_put_contents(
            $dir . '/payfast-notify.log',
            json_encode(['at' => date('c'), 'payload' => $payload], JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function resolveOrgIdForCheckout(array $user): string
    {
        $orgId = (string) ($user['organisation_id'] ?? '');
        if ($orgId !== '') {
            return $orgId;
        }

        if (Middleware::isAuthBypassed()) {
            return (string) ($_ENV['AUTH_BYPASS_ORGANISATION_ID'] ?? $_ENV['DEV_BYPASS_ORG_ID'] ?? '');
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

    private function resolveSelectedPaymentMethodId(string $orgId): ?string
    {
        $choice = trim((string) ($_POST['payment_method_choice'] ?? ''));
        if ($choice === '' || $choice === 'new') {
            return null;
        }

        try {
            $method = (new PaymentMethodService())->findById($choice, $orgId);
            if ($method && !empty($method['id'])) {
                return (string) $method['id'];
            }
        } catch (\Throwable $e) {
            error_log('checkout: payment method lookup failed: ' . $e->getMessage());
        }

        return null;
    }

    private function maybeSaveCardFromCheckout(array $user, string $orgId): ?string
    {
        $choice = trim((string) ($_POST['payment_method_choice'] ?? ''));
        $shouldSave = isset($_POST['save_card']) && (string) $_POST['save_card'] === '1';
        if ($choice !== 'new' || !$shouldSave) {
            return null;
        }

        $cardholderName = trim((string) ($_POST['cardholder_name'] ?? ''));
        $cardNumberRaw = (string) ($_POST['card_number'] ?? '');
        $expiryMonthRaw = trim((string) ($_POST['expiry_month'] ?? ''));
        $expiryYearRaw = trim((string) ($_POST['expiry_year'] ?? ''));
        $digits = preg_replace('/\D+/', '', $cardNumberRaw) ?? '';
        if ($cardholderName === '' || strlen($digits) < 12 || strlen($digits) > 19) {
            return null;
        }

        $monthInt = (int) $expiryMonthRaw;
        $yearInt = (int) $expiryYearRaw;
        $currentYear = (int) date('Y');
        if ($monthInt < 1 || $monthInt > 12 || $yearInt < $currentYear || $yearInt > ($currentYear + 25)) {
            return null;
        }

        return (new PaymentMethodService())->saveCard(
            $orgId,
            (string) ($user['id'] ?? ''),
            $this->detectCardBrand($digits),
            substr($digits, -4),
            str_pad((string) $monthInt, 2, '0', STR_PAD_LEFT),
            (string) $yearInt,
            $cardholderName,
            false
        ) ?: null;
    }

    private function payfastNameFields(array $user): array
    {
        return [
            (string) ($user['first_name'] ?? ''),
            (string) ($user['last_name'] ?? ''),
        ];
    }

    private function resolveCheckoutPlan(string $planId): array
    {
        $planService = new PlanService();
        $plan = $planService->findById($planId);
        if (!$plan) {
            $plan = $planService->findBySlug($planId);
        }
        if ($plan) {
            return $plan + ['_is_mock' => false];
        }

        $mockPlans = [
            'mock-starter' => ['id' => 'mock-starter', 'name' => 'Starter', 'price_monthly' => 450, 'credits_monthly' => 1000],
            'mock-pro' => ['id' => 'mock-pro', 'name' => 'Business Pro', 'price_monthly' => 1250, 'credits_monthly' => 5000],
            'mock-enterprise' => ['id' => 'mock-enterprise', 'name' => 'Enterprise', 'price_monthly' => 4500, 'credits_monthly' => 25000],
        ];

        return ($mockPlans[$planId] ?? $mockPlans['mock-pro']) + ['_is_mock' => true];
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
            if ($decodedKey !== '') {
                $data[$decodedKey] = urldecode($value);
            }
        }

        return $data;
    }

    private function stringPayload(array $payload): array
    {
        $stringPayload = [];
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $stringPayload[$key] = is_scalar($value) || $value === null ? (string) $value : '';
            }
        }

        return $stringPayload;
    }

    private function latestItnFeedbackForTransaction(array $tx, string $providerRef): array
    {
        $payload = $this->decodeJson($tx['raw_response'] ?? null);
        $itn = isset($payload['payfast_itn']) && is_array($payload['payfast_itn']) ? $payload['payfast_itn'] : [];
        $processing = isset($payload['itn_processing']) && is_array($payload['itn_processing']) ? $payload['itn_processing'] : [];

        if ($itn === [] && $providerRef === '') {
            return [];
        }

        return [
            'merchant_reference' => (string) ($itn['m_payment_id'] ?? $providerRef),
            'payfast_payment_id' => (string) ($itn['pf_payment_id'] ?? ($tx['provider_transaction_id'] ?? '')),
            'payfast_status' => (string) ($itn['payment_status'] ?? ($tx['payment_status'] ?? '')),
            'system_status' => (string) ($tx['status'] ?? ''),
            'amount_gross' => (string) ($itn['amount_gross'] ?? ''),
            'amount_net' => (string) ($itn['amount_net'] ?? ''),
            'http_status' => (string) ($processing['http_status'] ?? ''),
            'http_code' => (int) ($processing['http_code'] ?? 0),
            'response_body' => (string) ($processing['response_body'] ?? ''),
            'processed_at' => (string) ($processing['processed_at'] ?? ''),
        ];
    }

    private function decodeJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function index(): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');
        $planId = trim((string) ($_GET['plan_id'] ?? $_GET['plan'] ?? ''));

        if ($planId === '') {
            Session::flash('error', 'Please select a plan first.');
            header('Location: /plans');
            return;
        }

        try {
            $plan = $this->resolveCheckoutPlan($planId);
        } catch (\Throwable $e) {
            $plan = ['id' => 'mock-pro', 'name' => 'Business Pro', 'price_monthly' => 1250, 'credits_monthly' => 5000, '_is_mock' => true];
        }

        $paymentMethods = [];
        if ($orgId !== '') {
            try {
                $paymentMethods = (new PaymentMethodService())->getForOrganisation($orgId);
            } catch (\Throwable $e) {
                $paymentMethods = [];
            }
        }

        View::render('checkout/index', [
            'user' => $user,
            'plan' => $plan,
            'paymentMethods' => $paymentMethods,
            'checkoutIdempotencyKey' => bin2hex(random_bytes(16)),
        ]);
    }

    public function pay(): void
    {
        Middleware::auth();
        $user = Session::get('user') ?? [];
        $planId = trim((string) ($_POST['plan_id'] ?? $_POST['plan'] ?? ''));
        $orgId = $this->resolveOrgIdForCheckout($user);
        $billingCycle = (string) ($_POST['billing_cycle'] ?? 'monthly');
        $planName = (string) ($_POST['plan_name'] ?? 'plan');

        if ($planId === '') {
            Session::flash('error', 'Missing plan in checkout context.');
            header('Location: /plans');
            exit;
        }

        if ($orgId === '') {
            Session::flash('error', 'No organisation is linked to your account yet. Please complete organisation setup before checkout.');
            header('Location: /checkout?plan_id=' . urlencode($planId));
            exit;
        }

        $plan = $this->resolveCheckoutPlan($planId);
        $amount = (float) ($plan['price_monthly'] ?? 0);
        if ($billingCycle === 'annual') {
            $annual = (float) ($plan['price_annual'] ?? 0);
            $amount = $annual > 0 ? $annual : $amount * 12;
        }

        $paymentMethodId = $this->resolveSelectedPaymentMethodId($orgId)
            ?? $this->maybeSaveCardFromCheckout($user, $orgId);
        $idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'chk-' . hash('sha256', $orgId . '|' . $planId . '|' . $billingCycle);
        }

        $amountFormatted = number_format($amount, 2, '.', '');
        $checkout = (new PaymentTransactionService())->createPayfastCheckout([
            'organisation_id' => $orgId,
            'user_id' => (string) ($user['id'] ?? ''),
            'plan_id' => empty($plan['_is_mock']) ? (string) ($plan['id'] ?? $planId) : null,
            'requested_plan_id' => $planId,
            'plan_name' => $planName,
            'billing_cycle' => $billingCycle,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amountFormatted,
            'currency' => 'ZAR',
            'idempotency_key' => $idempotencyKey,
            'line_items' => [[
                'description' => $planName . ' Plan - ' . ucfirst($billingCycle) . ' Subscription',
                'quantity' => 1,
                'unit_price' => $amountFormatted,
                'tax_rate' => 0,
                'line_total' => $amountFormatted,
                'metadata' => [
                    'plan_id' => $plan['id'] ?? null,
                    'plan_slug' => $plan['slug'] ?? null,
                    'billing_cycle' => $billingCycle,
                    'credits_monthly' => $plan['credits_monthly'] ?? null,
                ],
            ]],
        ]);

        if (!$checkout) {
            Session::flash('error', 'Could not create checkout. Please try again.');
            header('Location: /checkout?plan_id=' . urlencode($planId));
            exit;
        }

        $merchantReference = (string) ($checkout['merchant_reference'] ?? $checkout['provider_ref'] ?? '');
        $invoiceId = (string) ($checkout['invoice_id'] ?? '');
        $paymentTransactionId = (string) ($checkout['id'] ?? $checkout['payment_transaction_id'] ?? '');
        $amountFormatted = number_format((float) ($checkout['amount'] ?? $amountFormatted), 2, '.', '');

        $this->saveJsonPayment([
            'provider_ref' => $merchantReference,
            'status' => (string) ($checkout['status'] ?? 'initiated'),
            'provider' => 'payfast',
            'organisation_id' => $orgId,
            'user_id' => (string) ($user['id'] ?? ''),
            'plan_id' => (string) ($plan['id'] ?? $planId),
            'requested_plan_id' => $planId,
            'plan_name' => $planName,
            'billing_cycle' => $billingCycle,
            'amount' => $amountFormatted,
            'currency' => 'ZAR',
            'invoice_id' => $invoiceId,
            'payment_transaction_id' => $paymentTransactionId,
            'payment_method_id' => $paymentMethodId,
        ]);

        if (!empty($checkout['checkout_url'])) {
            header('Location: ' . (string) $checkout['checkout_url']);
            exit;
        }

        if ($merchantReference === '') {
            Session::flash('error', 'Checkout was created without a PayFast reference.');
            header('Location: /checkout?plan_id=' . urlencode($planId));
            exit;
        }

        try {
            $payfast = PayFastService::fromEnv();
        } catch (InvalidArgumentException $e) {
            Session::flash('error', 'PayFast merchant credentials are not configured.');
            header('Location: /checkout?plan_id=' . urlencode($planId));
            exit;
        }

        [$payfastFirstName, $payfastLastName] = $this->payfastNameFields($user);
        $paymentData = $payfast->buildPaymentData([
            'm_payment_id' => $merchantReference,
            'amount' => $amountFormatted,
            'item_name' => $planName,
            'item_description' => 'Plan checkout for ' . $planName,
            'name_first' => $payfastFirstName,
            'name_last' => $payfastLastName,
            'email_address' => (string) ($user['email'] ?? ''),
            'custom_str1' => $orgId,
            'custom_str2' => (string) ($plan['id'] ?? $planId),
            'custom_str3' => $billingCycle,
            'custom_str4' => $invoiceId,
            'custom_str5' => $paymentTransactionId,
        ]);

        $gatewayUrl = $payfast->getProcessUrl();
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting to PayFast</title></head><body>';
        echo '<p style="font-family:Arial,sans-serif;">Redirecting to secure payment...</p>';
        echo '<form id="pf_redirect" method="post" action="' . htmlspecialchars($gatewayUrl, ENT_QUOTES) . '">';
        foreach ($paymentData as $key => $value) {
            echo '<input type="hidden" name="' . htmlspecialchars((string) $key, ENT_QUOTES) . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES) . '">';
        }
        echo '</form><script>document.getElementById("pf_redirect").submit();</script></body></html>';
        exit;
    }

    public function return(): void
    {
        $providerRef = trim((string) ($_GET['m_payment_id'] ?? $_GET['custom_str4'] ?? ''));
        $pfStatus = strtoupper(trim((string) ($_GET['payment_status'] ?? '')));
        $user = Session::get('user');

        $tx = [];
        if ($providerRef !== '') {
            try {
                $found = (new PaymentTransactionService())->findByProviderRef($providerRef);
                $tx = is_array($found) ? $found : [];
            } catch (\Throwable $e) {
                $tx = $this->getJsonPayment($providerRef);
            }
        } elseif (is_array($user)) {
            $tx = $this->findLatestJsonPaymentForUser((string) ($user['id'] ?? ''));
        }

        $status = strtolower((string) ($tx['status'] ?? ''));
        $paymentFeedback = $this->latestItnFeedbackForTransaction($tx, $providerRef);
        $outcome = 'pending';
        $message = 'Payment return received. Confirmation may still be processing.';

        if (in_array($status, ['successful', 'paid', 'complete', 'completed'], true) || $pfStatus === 'COMPLETE') {
            $outcome = 'success';
            $status = $status !== '' ? $status : 'successful';
            $message = 'Your payment was successful. Your subscription will update once confirmation is complete.';
        } elseif (in_array($status, ['failed', 'cancelled', 'canceled'], true) || in_array($pfStatus, ['FAILED', 'CANCELLED', 'CANCELED'], true)) {
            $outcome = str_contains($status, 'cancel') || str_contains(strtolower($pfStatus), 'cancel') ? 'cancelled' : 'failed';
            $message = $outcome === 'cancelled'
                ? 'Payment was cancelled before completion.'
                : 'Payment failed. Please try again or use another method.';
        } elseif ($providerRef === '' && $tx === []) {
            $outcome = 'unknown';
            $message = 'Payment status could not be matched to a checkout reference.';
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
            'paymentFeedback' => $paymentFeedback,
        ]);
    }

    public function cancel(): void
    {
        $user = Session::get('user');
        $providerRef = trim((string) ($_GET['m_payment_id'] ?? ''));
        if ($providerRef !== '') {
            $this->updateJsonPaymentStatus($providerRef, 'cancelled', ['cancelled_by' => 'user']);
            try {
                $txService = new PaymentTransactionService();
                $tx = $txService->findByProviderRef($providerRef);
                if (is_array($tx) && !empty($tx['id']) && in_array($tx['status'] ?? '', ['initiated', 'pending'], true)) {
                    $txService->markCancelled((string) $tx['id'], ['cancelled_by' => 'user']);
                }
            } catch (\Throwable $e) {
                error_log('checkout/cancel: could not mark cancellation: ' . $e->getMessage());
            }
        }

        View::render('checkout/cancel', ['user' => $user]);
    }

    public function notify(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $rawBody = (string) ($GLOBALS['GI_PAYFAST_RAW_POST'] ?? '');
        unset($GLOBALS['GI_PAYFAST_RAW_POST']);
        if ($rawBody === '') {
            $rawBody = file_get_contents('php://input') ?: '';
        }
        if ($rawBody === '' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST' && !empty($_POST)) {
            $rawBody = http_build_query($_POST);
        }

        $parsed = $this->parsePayFastRawPost($rawBody);
        if ($parsed === [] && !empty($_POST)) {
            $parsed = $this->stringPayload($_POST);
        }
        $stringPayload = $this->stringPayload($parsed);
        $this->appendNotifyLog($stringPayload);

        $txService = new PaymentTransactionService();
        $stringPayload = $txService->enrichItnPayload($stringPayload);
        $txService->recordPayfastItn($stringPayload, $rawBody, 'received', '', null);

        $signatureValid = true;
        $skipSignature = filter_var(
            getenv('PAYFAST_NOTIFY_SKIP_SIGNATURE') !== false
                ? getenv('PAYFAST_NOTIFY_SKIP_SIGNATURE')
                : ($_ENV['PAYFAST_NOTIFY_SKIP_SIGNATURE'] ?? ''),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$skipSignature) {
            $payfast = PayFastService::tryFromEnv();
            if ($payfast === null) {
                http_response_code(503);
                echo 'PayFast not configured';
                return;
            }

            $useXff = filter_var((string) (getenv('PAYFAST_USE_X_FORWARDED_FOR') ?: ($_ENV['PAYFAST_USE_X_FORWARDED_FOR'] ?? '')), FILTER_VALIDATE_BOOLEAN);
            $remoteAddr = '0.0.0.0';
            if ($useXff && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                $remoteAddr = trim($parts[0]);
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $remoteAddr = (string) $_SERVER['REMOTE_ADDR'];
            }

            $expectedAmount = null;
            $providerRefEarly = trim((string) ($stringPayload['m_payment_id'] ?? ''));
            if ($providerRefEarly !== '') {
                try {
                    $txEarly = $txService->findByProviderRef($providerRefEarly);
                    if (is_array($txEarly) && array_key_exists('amount', $txEarly)) {
                        $expectedAmount = number_format((float) $txEarly['amount'], 2, '.', '');
                    }
                } catch (\Throwable $e) {
                    $expectedAmount = null;
                }
            }

            $signatureValid = $payfast->validateItn(
                $stringPayload,
                $remoteAddr,
                $expectedAmount,
                $rawBody !== '' ? $rawBody : null
            );

            if (!$signatureValid) {
                $txService->recordPayfastItn($stringPayload, $rawBody, 'validation_failed', 'signature or source invalid', false);
                http_response_code(400);
                echo 'ITN validation failed';
                return;
            }
        }

        $missingFields = PaymentTransactionService::missingItnFields($stringPayload);
        if ($missingFields !== []) {
            $validationMessage = 'Missing ITN fields: ' . implode(', ', $missingFields);
            error_log('checkout/notify: ' . $validationMessage . ' payload=' . json_encode($stringPayload, JSON_UNESCAPED_SLASHES));
            $txService->recordPayfastItn($stringPayload, $rawBody, 'validation_failed', $validationMessage, $signatureValid);
            http_response_code(422);
            echo $validationMessage;
            return;
        }

        $providerRef = trim((string) ($stringPayload['m_payment_id'] ?? ''));
        if ($providerRef !== '') {
            $paymentStatus = strtolower((string) ($stringPayload['payment_status'] ?? ''));
            $this->updateJsonPaymentStatus($providerRef, $paymentStatus !== '' ? $paymentStatus : 'notified', $stringPayload);

            try {
                $existing = $txService->findByProviderRef($providerRef);
                if (is_array($existing) && in_array((string) ($existing['status'] ?? ''), ['successful', 'paid', 'complete', 'completed'], true)) {
                    $txService->recordPayfastItn($stringPayload, $rawBody, 'processed', 'Already successful (idempotent replay)', $signatureValid);
                    http_response_code(200);
                    echo 'OK';
                    return;
                }
            } catch (\Throwable $e) {
                // Continue to ITN processing when lookup fails.
            }
        }

        try {
            $result = $txService->processPayfastItn($stringPayload, $rawBody, $signatureValid);
            $code = $result['http_code'] > 0 ? $result['http_code'] : 200;
            $message = $result['message'] !== '' ? $result['message'] : 'OK';
            if ($code >= 400) {
                $detail = $result['body'] !== [] ? json_encode($result['body'], JSON_UNESCAPED_SLASHES) : '';
                error_log('checkout/notify ITN failed http=' . $code . ' message=' . $message . ' body=' . $detail);

                if ($code === 500 && str_contains($message, 'payfast_itn_logs_pf_payment_processed_uidx')) {
                    $txService->recordPayfastItn($stringPayload, $rawBody, 'processed', 'Duplicate pf_payment_id treated as idempotent replay', $signatureValid);
                    http_response_code(200);
                    echo 'OK';
                    return;
                }
            }
            $txService->recordPayfastItn(
                $stringPayload,
                $rawBody,
                $code === 200 ? 'processed' : ($code >= 400 ? 'failed' : 'partial'),
                $message,
                $signatureValid
            );
            http_response_code($code);
            echo $message;
        } catch (\Throwable $e) {
            error_log('checkout/notify failed: ' . $e->getMessage());
            $txService->recordPayfastItn($stringPayload, $rawBody, 'error', substr($e->getMessage(), 0, 4000), $signatureValid);
            http_response_code(500);
            echo 'ITN error';
        }
    }
}
