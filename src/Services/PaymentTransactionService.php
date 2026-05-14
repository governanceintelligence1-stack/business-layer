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
        array $payload = [],
        ?string $invoiceId = null
    ): string {
        $sql = "INSERT INTO payment_transactions (
                    organisation_id,
                    invoice_id,
                    payment_method_id,
                    provider,
                    merchant_reference,
                    idempotency_key,
                    amount,
                    currency,
                    status,
                    raw_response,
                    created_at,
                    updated_at
                ) VALUES (
                    :organisation_id,
                    :invoice_id,
                    :payment_method_id,
                    'payfast',
                    :merchant_reference,
                    :idempotency_key,
                    :amount,
                    'ZAR',
                    'initiated',
                    :raw_response::jsonb,
                    :created_at,
                    :updated_at
                ) RETURNING id";

        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([
            'organisation_id'   => $orgId,
            'invoice_id'        => $invoiceId,
            'payment_method_id' => $paymentMethodId,
            'merchant_reference' => $providerRef,
            'idempotency_key'   => $providerRef,
            'amount'            => $amount,
            'raw_response'      => json_encode([
                'user_id' => $userId ?: null,
                'plan_id' => $planId ?: null,
                'payload' => $payload,
            ], JSON_UNESCAPED_SLASHES),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? (string)($result['id'] ?? '') : '';
    }

    /**
     * Row lock for ITN idempotency (call inside an open transaction).
     */
    public function fetchByMerchantReferenceForUpdate(string $providerRef): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM payment_transactions WHERE merchant_reference = :ref FOR UPDATE',
            ['ref' => $providerRef]
        );
    }

    public function findByProviderRef(string $providerRef): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM payment_transactions WHERE merchant_reference = :ref',
            ['ref' => $providerRef]
        );
    }

    public function markPaid(string $id, array $payload): int
    {
        return $this->db->update('payment_transactions', [
            'status'      => 'successful',
            'raw_response' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markCancelled(string $id, array $payload): int
    {
        return $this->db->update('payment_transactions', [
            'status'      => 'cancelled',
            'raw_response' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markFailed(string $id, array $payload): int
    {
        return $this->db->update('payment_transactions', [
            'status'      => 'failed',
            'raw_response' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function markActivated(string $id): int
    {
        return $this->db->update('payment_transactions', [
            'status'       => 'successful',
            'updated_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    /**
     * Mark successful after activation, merge ITN into raw_response, link invoice.
     *
     * @param array<string, mixed> $itnPayload
     */
    public function markSuccessfulWithItn(string $id, array $itnPayload, ?string $invoiceId = null): int
    {
        $row = $this->db->fetch('SELECT raw_response FROM payment_transactions WHERE id = :id', ['id' => $id]);
        $existing = [];
        if (!empty($row['raw_response']) && is_string($row['raw_response'])) {
            $decoded = json_decode($row['raw_response'], true);
            $existing = is_array($decoded) ? $decoded : [];
        }

        $merged = array_merge($existing, ['payfast_itn' => $itnPayload]);
        $pfId = trim((string)($itnPayload['pf_payment_id'] ?? ''));

        $data = [
            'status'                 => 'successful',
            'raw_response'           => json_encode($merged, JSON_UNESCAPED_SLASHES),
            'updated_at'             => date('Y-m-d H:i:s'),
            'provider_transaction_id' => $pfId !== '' ? $pfId : null,
        ];
        if ($invoiceId !== null && $invoiceId !== '') {
            $data['invoice_id'] = $invoiceId;
        }

        return $this->db->update('payment_transactions', $data, ['id' => $id]);
    }
}
