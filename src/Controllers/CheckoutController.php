<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Core\DB;
use GI\Services\BillingService;
use GI\Services\PayFastService;
use GI\Services\PlanService;
use GI\Services\PaymentTransactionService;
use GI\Services\PaymentMethodService;
use GI\Services\SubscriptionService;
use GI\Services\CreditService;

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
            $amount = (float) (($plan['price_monthly'] ?? 0) * 12);
        }
        error_log("[{$dbgId}] billing_cycle={$billingCycle}, amount={$amount}");

        $persistedPlanId = (!empty($plan['_is_mock']) ? '' : (string)($plan['id'] ?? ''));
        $providerRef = $this->generateManualPaymentId();
        error_log("[{$dbgId}] creating payment transaction provider_ref={$providerRef}, persisted_plan_id=" . ($persistedPlanId !== '' ? $persistedPlanId : 'NULL'));
        try {
            $txService = new PaymentTransactionService();
            $txService->createPending(
                $orgId,
                (string)($user['id'] ?? ''),
                $persistedPlanId,
                $paymentMethodId,
                $providerRef,
                $amount,
                [
                    'billing_cycle' => $billingCycle,
                    'plan_name' => $planName,
                    'requested_plan_id' => $planId,
                    'is_mock_plan' => !empty($plan['_is_mock']),
                    'payment_method_id' => $paymentMethodId,
                ]
            );
        } catch (\Exception $e) {
            if (!$this->isAuthBypassed()) {
                throw $e;
            }
            error_log("[{$dbgId}] createPending failed in AUTH_BYPASS mode: " . $e->getMessage());
        }
        $this->saveJsonPayment([
            'provider_ref' => $providerRef,
            'status' => 'pending',
            'provider' => 'payfast',
            'organisation_id' => $orgId,
            'user_id' => (string)($user['id'] ?? ''),
            'plan_id' => $persistedPlanId,
            'requested_plan_id' => $planId,
            'plan_name' => $planName,
            'billing_cycle' => $billingCycle,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'ZAR',
        ]);
        error_log("[{$dbgId}] payment transaction created");

        $payfast = new PayFastService();
        $paymentData = $payfast->buildPaymentData([
            'm_payment_id'   => $providerRef,
            'amount'         => number_format($amount, 2, '.', ''),
            'item_name'      => $planName,
            'item_description' => 'Plan checkout for ' . $planName,
            'name_first'     => (string)($user['first_name'] ?? ''),
            'name_last'      => (string)($user['last_name'] ?? ''),
            'email_address'  => (string)($user['email'] ?? ''),
            'custom_str1'    => $orgId,
            'custom_str2'    => $planId,
            'custom_str3'    => $billingCycle,
        ]);
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
        Middleware::auth();
        $providerRef = trim((string)($_GET['m_payment_id'] ?? $_GET['custom_str4'] ?? ''));
        $user = Session::get('user');

        $status = 'pending';
        $message = 'Payment received and pending confirmation. Please refresh shortly.';

        if ($providerRef === '') {
            $fallback = $this->findLatestJsonPaymentForUser((string)($user['id'] ?? ''));
            if (!empty($fallback['provider_ref'])) {
                $providerRef = (string)$fallback['provider_ref'];
            }
        }

        if ($providerRef !== '') {
            $tx = [];
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

            if ($tx !== []) {
                $status = (string)($tx['status'] ?? 'pending');
                if (in_array($status, ['successful', 'paid', 'complete'], true)) {
                    $message = 'Your payment is marked successful.';
                } elseif (in_array($status, ['failed', 'cancelled'], true)) {
                    $message = 'Payment is not successful. You can retry from plans.';
                } else {
                    $message = 'Payment received and pending confirmation. Please refresh shortly.';
                }
            } else {
                $status = 'unknown';
                $message = 'Payment reference not found. Please contact support if this persists.';
            }
        } else {
            $status = 'pending';
            $message = 'Payment return received. Confirmation may still be processing.';
        }

        View::render('checkout/return', [
            'user' => $user,
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function cancel(): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $providerRef = trim((string)($_GET['m_payment_id'] ?? ''));
        if ($providerRef !== '') {
            $this->updateJsonPaymentStatus($providerRef, 'cancelled', ['cancelled_by' => 'user']);
            try {
                $txService = new PaymentTransactionService();
                $tx = $txService->findByProviderRef($providerRef);
                if (is_array($tx) && !empty($tx['id'])) {
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
        $payload = $_POST ?: [];
        $this->appendNotifyLog($payload);

        $skipSignature = filter_var(
            getenv('PAYFAST_NOTIFY_SKIP_SIGNATURE') !== false
                ? getenv('PAYFAST_NOTIFY_SKIP_SIGNATURE')
                : ($_ENV['PAYFAST_NOTIFY_SKIP_SIGNATURE'] ?? ''),
            FILTER_VALIDATE_BOOLEAN
        );
        $payfast = new PayFastService();
        if (!$skipSignature && !$payfast->isValidSignature($payload)) {
            http_response_code(400);
            echo 'Invalid signature';
            return;
        }

        $providerRef = trim((string)($payload['m_payment_id'] ?? ''));
        $paymentStatus = strtolower((string)($payload['payment_status'] ?? ''));
        if ($providerRef !== '') {
            $status = $paymentStatus !== '' ? $paymentStatus : 'notified';
            $this->updateJsonPaymentStatus($providerRef, $status, $payload);
        }

        if ($providerRef === '') {
            http_response_code(200);
            echo 'OK';
            return;
        }

        if (!in_array($paymentStatus, ['complete', 'completed'], true)) {
            http_response_code(200);
            echo 'OK';
            return;
        }

        $txService = new PaymentTransactionService();
        $tx = $txService->findByProviderRef($providerRef);
        if (!$tx || empty($tx['id'])) {
            http_response_code(200);
            echo 'OK';
            return;
        }

        if (($tx['status'] ?? '') === 'successful') {
            http_response_code(200);
            echo 'OK';
            return;
        }

        try {
            $invoiceId = $this->activateFromPayment($tx);
            if ($invoiceId !== null && $invoiceId !== '') {
                $txService->markSuccessfulWithItn((string) $tx['id'], $payload, $invoiceId);
            }
        } catch (\Throwable $e) {
            error_log('checkout/notify activation failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'Activation failed';
            return;
        }

        http_response_code(200);
        echo 'OK';
    }

    /**
     * Creates subscription, paid invoice, and credit grant. Returns invoice id or null if nothing to do.
     */
    private function activateFromPayment(array $tx): ?string
    {
        $orgId = (string)($tx['organisation_id'] ?? '');
        $payloadData = $this->decodeTxPayload($tx['raw_response'] ?? ($tx['raw_payload'] ?? null));
        $planId = (string)($payloadData['plan_id'] ?? '');
        $payload = isset($payloadData['payload']) && is_array($payloadData['payload'])
            ? $payloadData['payload']
            : $payloadData;
        if ($planId === '') {
            $planId = $this->resolvePlanIdForMockPayment($payload);
        }
        if ($orgId === '' || $planId === '') {
            return null;
        }

        $subscriptionService = new SubscriptionService();
        $creditService = new CreditService();
        $billingService = new BillingService();
        $planService = new PlanService();

        $existing = $subscriptionService->getActive($orgId);
        if ($existing) {
            $subscriptionService->cancel((string)$existing['id']);
        }

        $billingCycle = (string)($payload['billing_cycle'] ?? 'monthly');
        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            $billingCycle = 'monthly';
        }
        $subscriptionService->create($orgId, $planId, $billingCycle);
        $plan = $planService->findById($planId);
        $planName = (string)($plan['name'] ?? 'Plan');
        $monthlyCredits = (float)($plan['credits_monthly'] ?? 0);
        $amount = (float)($tx['amount'] ?? 0);

        $invoiceId = $billingService->createInvoice($orgId, [[
            'description' => $planName . ' subscription',
            'quantity'    => 1,
            'unit_price'  => $amount,
            'tax_rate'    => 0,
            'line_total'  => $amount,
        ]]);
        $billingService->markPaid($invoiceId);

        if ($monthlyCredits > 0) {
            $creditService->addCredits($orgId, $monthlyCredits, 'Plan activation credits', 'subscription', $planId);
        }

        return $invoiceId;
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
