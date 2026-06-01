<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

/**
 * Guards payment ITN handling and fulfillment from duplicate side effects.
 */
class PaymentIdempotencyService
{
    public const REF_TYPE_PAYMENT_FULFILLMENT = 'payment_fulfillment';

    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    /**
     * True when this PayFast payment id was already processed successfully for this merchant reference.
     */
    public function isPayfastPaymentAlreadyFulfilled(string $pfPaymentId, string $merchantReference): bool
    {
        $pfPaymentId = trim($pfPaymentId);
        $merchantReference = trim($merchantReference);
        if ($pfPaymentId === '') {
            return false;
        }

        if ($this->isPfPaymentIdInProcessedItnLogs($pfPaymentId, $merchantReference)) {
            return true;
        }

        if ($merchantReference !== '') {
            $tx = $this->db->fetch(
                'SELECT status, raw_response FROM payment_transactions WHERE merchant_reference = :ref LIMIT 1',
                ['ref' => $merchantReference]
            );
            if (is_array($tx) && ($tx['status'] ?? '') === 'successful') {
                $payload = $this->decodeJson($tx['raw_response'] ?? null);
                $existingPf = trim((string) ($payload['payfast_itn']['pf_payment_id'] ?? ''));
                if ($existingPf !== '' && $existingPf === $pfPaymentId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Atomically mark invoice token grant as claimed. Returns false if already granted.
     */
    public function tryClaimInvoiceTokenGrant(string $invoiceId): bool
    {
        $invoiceId = trim($invoiceId);
        if ($invoiceId === '') {
            return false;
        }

        if (!$this->hasInvoiceColumn('credits_granted')) {
            return true;
        }

        $pdo = $this->db->getPdo();
        $sets = ['credits_granted = true'];
        if ($this->hasInvoiceColumn('credits_granted_at')) {
            $sets[] = 'credits_granted_at = NOW()';
        }
        if ($this->hasInvoiceColumn('updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE billing_invoices SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND (credits_granted IS NULL OR credits_granted = false)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $invoiceId]);

        return $stmt->rowCount() > 0;
    }

    public function isInvoiceTokenGrantClaimed(string $invoiceId): bool
    {
        if (!$this->hasInvoiceColumn('credits_granted')) {
            return false;
        }

        $row = $this->db->fetch(
            'SELECT credits_granted FROM billing_invoices WHERE id = :id',
            ['id' => $invoiceId]
        );

        return !empty($row['credits_granted']);
    }

    /**
     * Reuse an in-flight checkout when the client resubmits with the same idempotency key.
     *
     * @return array<string, mixed>|false
     */
    public function findReusableCheckoutTransaction(string $orgId, string $idempotencyKey): array|false
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($orgId === '' || $idempotencyKey === '') {
            return false;
        }

        if (!$this->hasPaymentTxColumn('idempotency_key')) {
            return false;
        }

        return $this->db->fetch(
            "SELECT *
             FROM payment_transactions
             WHERE organisation_id = :org_id
               AND idempotency_key = :idem
               AND status IN ('initiated', 'pending')
             ORDER BY created_at DESC
             LIMIT 1",
            ['org_id' => $orgId, 'idem' => $idempotencyKey]
        );
    }

    private function isPfPaymentIdInProcessedItnLogs(string $pfPaymentId, string $merchantReference): bool
    {
        if (!$this->tableExists('payfast_itn_logs') || !$this->hasItnLogColumn('pf_payment_id')) {
            return false;
        }

        $statusCol = $this->hasItnLogColumn('processing_status') ? 'processing_status' : 'status';
        $sql = "SELECT id FROM payfast_itn_logs
                WHERE pf_payment_id = :pf
                  AND ({$statusCol} = 'processed' OR {$statusCol} = 'partial')
                ";
        $params = ['pf' => $pfPaymentId];
        if ($merchantReference !== '' && $this->hasItnLogColumn('merchant_reference')) {
            $sql .= ' AND merchant_reference = :ref';
            $params['ref'] = $merchantReference;
        }
        $sql .= ' LIMIT 1';

        $row = $this->db->fetch($sql, $params);

        return $row !== false;
    }

    private function decodeJson(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function tableExists(string $table): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1 FROM information_schema.tables
                 WHERE table_schema = 'public' AND table_name = :table LIMIT 1",
                ['table' => $table]
            );

            return $row !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasInvoiceColumn(string $column): bool
    {
        return $this->hasColumn('billing_invoices', $column);
    }

    private function hasPaymentTxColumn(string $column): bool
    {
        return $this->hasColumn('payment_transactions', $column);
    }

    private function hasItnLogColumn(string $column): bool
    {
        return $this->hasColumn('payfast_itn_logs', $column);
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema = 'public' AND table_name = :table AND column_name = :column LIMIT 1",
                ['table' => $table, 'column' => $column]
            );

            return $row !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
