<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class CreditService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    /**
     * Map external job identifiers to a stable UUID for `job_reservations.job_id`.
     * RFC 4122 UUID v5 (URL namespace), no external dependency (PHP 8.0-safe).
     */
    private function normalizeJobUuid(string $jobId): string
    {
        if (self::isUuidString($jobId)) {
            return strtolower($jobId);
        }

        return self::uuid5UrlNamespace('gi.job:' . $jobId);
    }

    public function getOrCreateAccount(string $orgId): array
    {
        $account = $this->db->fetch(
            'SELECT * FROM credit_accounts WHERE organisation_id = :org_id',
            ['org_id' => $orgId]
        );

        if (!$account) {
            $this->db->insert('credit_accounts', [
                'organisation_id' => $orgId,
                'balance'         => 0,
                'reserved'        => 0,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
            return ['balance' => '0.00', 'reserved' => '0.00', 'organisation_id' => $orgId];
        }

        return $account;
    }

    public function getBalance(string $orgId): float
    {
        $account = $this->getOrCreateAccount($orgId);
        return (float) $account['balance'];
    }

    public function getAvailableBalance(string $orgId): float
    {
        $account = $this->getOrCreateAccount($orgId);
        return (float) $account['balance'] - (float) $account['reserved'];
    }

    public function addCredits(
        string $orgId,
        float $amount,
        string $description,
        string $refType = '',
        string $refId = '',
        ?string $createdByUserId = null
    ): bool {
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $account = $this->db->fetch(
                'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                ['org_id' => $orgId]
            );
            if (!$account) {
                $pdo->rollBack();
                $this->getOrCreateAccount($orgId);
                $pdo->beginTransaction();
                $account = $this->db->fetch(
                    'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                    ['org_id' => $orgId]
                );
            }

            $newBalance = (float) $account['balance'] + $amount;
            $reserved = (float) $account['reserved'];

            $this->db->update('credit_accounts', [
                'balance'    => $newBalance,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $ledgerType = match ($refType) {
                'subscription' => 'subscription_credit',
                'topup', 'payment' => 'credit_topup',
                default => 'credit_grant',
            };
            $refUuid = $this->parseOptionalUuid($refId);

            $this->insertCreditTransaction(
                $orgId,
                (string) $account['id'],
                $ledgerType,
                $amount,
                $newBalance,
                $reserved,
                $refType !== '' ? $refType : null,
                $refUuid,
                $description,
                [],
                $createdByUserId
            );

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deductCredits(
        string $orgId,
        float $amount,
        string $description,
        string $refType = '',
        string $refId = '',
        ?string $createdByUserId = null
    ): bool {
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $account = $this->db->fetch(
                'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                ['org_id' => $orgId]
            );
            if (!$account) {
                $pdo->rollBack();
                $this->getOrCreateAccount($orgId);
                $pdo->beginTransaction();
                $account = $this->db->fetch(
                    'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                    ['org_id' => $orgId]
                );
            }

            if ((float) $account['balance'] < $amount) {
                $pdo->rollBack();
                throw new \RuntimeException('Insufficient credits');
            }

            $newBalance = (float) $account['balance'] - $amount;
            $reserved = (float) $account['reserved'];

            $this->db->update('credit_accounts', [
                'balance'    => $newBalance,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $this->insertCreditTransaction(
                $orgId,
                (string) $account['id'],
                'debit_usage',
                $amount,
                $newBalance,
                $reserved,
                $refType !== '' ? $refType : null,
                $this->parseOptionalUuid($refId),
                $description,
                $refId !== '' && !self::isUuidString($refId) ? ['external_ref' => $refId] : [],
                $createdByUserId
            );

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function reserveCredits(string $orgId, float $amount, string $jobId): bool
    {
        $jobUuid = $this->normalizeJobUuid($jobId);
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $this->getOrCreateAccount($orgId);
            $account = $this->db->fetch(
                'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                ['org_id' => $orgId]
            );
            if (!$account) {
                $pdo->rollBack();
                throw new \RuntimeException('Credit account missing');
            }

            $available = (float) $account['balance'] - (float) $account['reserved'];
            if ($available < $amount) {
                $pdo->rollBack();
                throw new \RuntimeException('Insufficient available credits');
            }

            $newReserved = (float) $account['reserved'] + $amount;

            $this->db->update('credit_accounts', [
                'reserved'   => $newReserved,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $reservationRef = 'RES-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));

            $meta = $jobUuid !== $jobId ? ['client_job_id' => $jobId] : [];
            $reservationId = $this->db->insert('job_reservations', [
                'organisation_id'       => $orgId,
                'job_id'                => $jobUuid,
                'reservation_reference' => $reservationRef,
                'estimated_credits'     => $amount,
                'status'                => 'reserved',
                'metadata'              => json_encode($meta ?: new \stdClass(), JSON_UNESCAPED_SLASHES),
            ]);

            $this->insertCreditTransaction(
                $orgId,
                (string) $account['id'],
                'reserve',
                $amount,
                (float) $account['balance'],
                $newReserved,
                'job_reservation',
                $this->parseOptionalUuid((string) $reservationId),
                'Credit reservation for job',
                ['reservation_reference' => $reservationRef, 'job_id' => $jobUuid],
                null
            );

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function releaseReservation(string $jobId): bool
    {
        $jobUuid = $this->normalizeJobUuid($jobId);
        $reservation = $this->db->fetch(
            "SELECT * FROM job_reservations WHERE job_id = CAST(:job_id AS uuid) AND status = 'reserved'",
            ['job_id' => $jobUuid]
        );

        if (!$reservation) {
            return false;
        }

        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $orgId = (string) $reservation['organisation_id'];
            $account = $this->db->fetch(
                'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                ['org_id' => $orgId]
            );
            if (!$account) {
                $pdo->rollBack();
                return false;
            }

            $releaseAmount = (float) $reservation['estimated_credits'];
            $computed = (float) $account['reserved'] - $releaseAmount;
            if ($computed < 0) {
                error_log("WARNING: reserved credits went negative for org {$orgId} (computed: {$computed}). Clamping to 0.");
            }
            $newReserved = max(0, $computed);

            $this->db->update('credit_accounts', [
                'reserved'   => $newReserved,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $this->db->update('job_reservations', [
                'status'     => 'released',
                'released_at' => date('Y-m-d H:i:s'),
            ], ['id' => $reservation['id']]);

            $this->insertCreditTransaction(
                $orgId,
                (string) $account['id'],
                'release',
                $releaseAmount,
                (float) $account['balance'],
                $newReserved,
                'job_reservation',
                $this->parseOptionalUuid((string) $reservation['id']),
                'Credit reservation released',
                ['job_id' => $jobUuid],
                null
            );

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function finalizeReservation(string $jobId, float $actualAmount): bool
    {
        $jobUuid = $this->normalizeJobUuid($jobId);
        $reservation = $this->db->fetch(
            "SELECT * FROM job_reservations WHERE job_id = CAST(:job_id AS uuid) AND status = 'reserved'",
            ['job_id' => $jobUuid]
        );

        if (!$reservation) {
            return false;
        }

        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $orgId = (string) $reservation['organisation_id'];
            $account = $this->db->fetch(
                'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                ['org_id' => $orgId]
            );
            if (!$account) {
                $pdo->rollBack();
                return false;
            }

            $reservedAmount = (float) $reservation['estimated_credits'];
            $newReserved = max(0, (float) $account['reserved'] - $reservedAmount);
            $newBalance = (float) $account['balance'] - $actualAmount;

            if ($newBalance < 0) {
                $pdo->rollBack();
                throw new \RuntimeException('Insufficient credits to finalize reservation');
            }

            $this->db->update('credit_accounts', [
                'balance'    => $newBalance,
                'reserved'   => $newReserved,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $this->db->update('job_reservations', [
                'status'         => 'captured',
                'actual_credits' => $actualAmount,
                'captured_at'    => date('Y-m-d H:i:s'),
            ], ['id' => $reservation['id']]);

            $this->insertCreditTransaction(
                $orgId,
                (string) $account['id'],
                'capture',
                $actualAmount,
                $newBalance,
                $newReserved,
                'job_reservation',
                $this->parseOptionalUuid((string) $reservation['id']),
                "Job {$jobId} finalized",
                ['job_id' => $jobUuid],
                null
            );

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getTransactionHistory(string $orgId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM credit_transactions WHERE organisation_id = :org_id
             ORDER BY created_at DESC LIMIT :lim',
            ['org_id' => $orgId, 'lim' => $limit]
        );
    }

    private function parseOptionalUuid(string $id): ?string
    {
        $id = trim($id);
        if ($id === '' || !self::isUuidString($id)) {
            return null;
        }
        return $id;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function insertCreditTransaction(
        string $orgId,
        string $creditAccountId,
        string $type,
        float $amount,
        float $balanceAfter,
        float $reservedAfter,
        ?string $refType,
        ?string $refId,
        string $description,
        array $metadata,
        ?string $createdByUserId
    ): void {
        $row = [
            'organisation_id'   => $orgId,
            'credit_account_id' => $creditAccountId,
            'type'              => $type,
            'amount'            => $amount,
            'balance_after'     => $balanceAfter,
            'reserved_after'    => $reservedAfter,
            'ref_type'          => $refType,
            'ref_id'            => $refId,
            'description'       => $description,
            'metadata'          => json_encode($metadata ?: new \stdClass(), JSON_UNESCAPED_SLASHES),
        ];
        if ($createdByUserId !== null && $createdByUserId !== '' && self::isUuidString($createdByUserId)) {
            $row['created_by'] = $createdByUserId;
        }

        $this->db->insert('credit_transactions', $row);
    }

    private static function isUuidString(string $id): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }

    /**
     * UUID v5 with RFC 4122 URL namespace (same as Ramsey NAMESPACE_URL).
     */
    private static function uuid5UrlNamespace(string $name): string
    {
        $ns = hex2bin(str_replace('-', '', '6ba7b811-9dad-11d1-80b4-00c04fd430c8'));
        $hash = sha1($ns . $name, true);
        $bytes = substr($hash, 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
