<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class WebhookService
{
    private DB $db;
    private SubscriptionService $subscriptionService;
    private CreditService $creditService;

    public function __construct()
    {
        $this->db                  = DB::getInstance();
        $this->subscriptionService = new SubscriptionService();
        $this->creditService       = new CreditService();
    }

    public function handle(string $payload, string $signature): bool
    {
        $data = json_decode($payload, true);
        if (!$data) {
            return false;
        }

        $event = $data['type'] ?? '';

        return match ($event) {
            'payment.success'        => $this->processPaymentSuccess($data),
            'subscription.updated'   => $this->processSubscriptionUpdate($data),
            'subscription.cancelled' => $this->processSubscriptionCancelled($data),
            default                  => false,
        };
    }

    public function processPaymentSuccess(array $data): bool
    {
        $orgId  = $data['organisation_id'] ?? '';
        $amount = (float) ($data['credits'] ?? 0);
        $refId  = $data['transaction_id'] ?? '';

        if (empty($orgId) || $amount <= 0) {
            return false;
        }

        $this->creditService->addCredits($orgId, $amount, 'Payment received', 'payment', $refId);
        return true;
    }

    public function processSubscriptionUpdate(array $data): bool
    {
        $orgId  = $data['organisation_id'] ?? '';
        $planId = $data['plan_id'] ?? '';

        if (empty($orgId) || empty($planId)) {
            return false;
        }

        $existing = $this->subscriptionService->getActive($orgId);
        if ($existing) {
            $this->subscriptionService->cancel($existing['id']);
        }

        $this->subscriptionService->create($orgId, $planId, $data['billing_cycle'] ?? 'monthly');
        return true;
    }

    public function processSubscriptionCancelled(array $data): bool
    {
        $orgId = $data['organisation_id'] ?? '';
        if (empty($orgId)) {
            return false;
        }

        $sub = $this->subscriptionService->getActive($orgId);
        if ($sub) {
            $this->subscriptionService->cancel($sub['id']);
        }
        return true;
    }

    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }
}
