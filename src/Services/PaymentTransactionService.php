<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class PaymentTransactionService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function createPending(
        string $orgId,
        string $userId,
        string $planId,
        ?string $paymentMethodId,
        string $providerRef,
        float $amount,
        array $payload = []
    ): string {
        return $this->db->insert('payment_transactions', [
            'organisation_id'   => $orgId,
            'user_id'           => $userId ?: null,
            'plan_id'           => $planId ?: null,
            'payment_method_id' => $paymentMethodId,
            'provider'          => 'payfast',
            'provider_ref'      => $providerRef,
            'amount'            => $amount,
            'currency'          => 'ZAR',
            'status'            => 'pending',
            'raw_payload'       => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByProviderRef(string $providerRef): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM payment_transactions WHERE provider_ref = :ref',
            ['ref' => $providerRef]
        );
    }

    public function markPaid(string $id, array $payload): int
    {
        return $this->db->update('payment_transactions', [
            'status'      => 'paid',
            'raw_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markCancelled(string $id, array $payload): int
    {
        return $this->db->update('payment_transactions', [
            'status'      => 'cancelled',
            'raw_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markFailed(string $id, array $payload): int
    {
        return $this->db->update('payment_transactions', [
            'status'      => 'failed',
            'raw_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markActivated(string $id): int
    {
        return $this->db->update('payment_transactions', [
            'activated_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }
}
