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
            return ['balance' => '0.0000', 'reserved' => '0.0000', 'organisation_id' => $orgId];
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

    public function addCredits(string $orgId, float $amount, string $description, string $refType = '', string $refId = ''): bool
    {
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

            $this->db->update('credit_accounts', [
                'balance'    => $newBalance,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $this->recordTransaction($orgId, 'credit', $amount, $newBalance, $description, $refType, $refId);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deductCredits(string $orgId, float $amount, string $description, string $refType = '', string $refId = ''): bool
    {
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

            $this->db->update('credit_accounts', [
                'balance'    => $newBalance,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $this->recordTransaction($orgId, 'debit', $amount, $newBalance, $description, $refType, $refId);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function reserveCredits(string $orgId, float $amount, string $jobId): bool
    {
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $account   = $this->getOrCreateAccount($orgId);
            $available = (float) $account['balance'] - (float) $account['reserved'];

            if ($available < $amount) {
                $pdo->rollBack();
                throw new \RuntimeException('Insufficient available credits');
            }

            $this->db->update('credit_accounts', [
                'reserved'   => (float) $account['reserved'] + $amount,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $this->db->insert('job_reservations', [
                'organisation_id'  => $orgId,
                'job_id'           => $jobId,
                'reserved_credits' => $amount,
                'status'           => 'reserved',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function releaseReservation(string $jobId): bool
    {
        $reservation = $this->db->fetch(
            "SELECT * FROM job_reservations WHERE job_id = :job_id AND status = 'reserved'",
            ['job_id' => $jobId]
        );

        if (!$reservation) {
            return false;
        }

        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $account        = $this->getOrCreateAccount($reservation['organisation_id']);
            $computed       = (float) $account['reserved'] - (float) $reservation['reserved_credits'];
            if ($computed < 0) {
                error_log("WARNING: reserved credits went negative for org {$reservation['organisation_id']} (computed: $computed). Clamping to 0.");
            }
            $newReserved = max(0, $computed);

            $this->db->update('credit_accounts', [
                'reserved'   => $newReserved,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $reservation['organisation_id']]);

            $this->db->update('job_reservations', [
                'status'       => 'released',
                'finalized_at' => date('Y-m-d H:i:s'),
            ], ['job_id' => $jobId]);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function finalizeReservation(string $jobId, float $actualAmount): bool
    {
        $reservation = $this->db->fetch(
            "SELECT * FROM job_reservations WHERE job_id = :job_id AND status = 'reserved'",
            ['job_id' => $jobId]
        );

        if (!$reservation) {
            return false;
        }

        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $orgId       = $reservation['organisation_id'];
            $account     = $this->getOrCreateAccount($orgId);
            $newReserved = max(0, (float) $account['reserved'] - (float) $reservation['reserved_credits']);
            $newBalance  = (float) $account['balance'] - $actualAmount;

            $this->db->update('credit_accounts', [
                'balance'    => $newBalance,
                'reserved'   => $newReserved,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['organisation_id' => $orgId]);

            $this->db->update('job_reservations', [
                'status'         => 'finalized',
                'actual_credits' => $actualAmount,
                'finalized_at'   => date('Y-m-d H:i:s'),
            ], ['job_id' => $jobId]);

            $this->recordTransaction(
                $orgId, 'debit', $actualAmount, $newBalance,
                "Job {$jobId} finalized", 'job', $jobId
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

    private function recordTransaction(
        string $orgId, string $type, float $amount, float $balanceAfter,
        string $description, string $refType = '', string $refId = ''
    ): void {
        $this->db->insert('credit_transactions', [
            'organisation_id' => $orgId,
            'type'            => $type,
            'amount'          => $amount,
            'balance_after'   => $balanceAfter,
            'description'     => $description,
            'ref_type'        => $refType,
            'ref_id'          => $refId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }
}
