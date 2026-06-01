<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class TokenService
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

    public function getReservedBalance(string $orgId): float
    {
        $account = $this->getOrCreateAccount($orgId);

        return (float) ($account['reserved'] ?? 0);
    }

    /**
     * @return array{balance: float, reserved: float, available: float}
     */
    public function getAccountSummary(string $orgId): array
    {
        $account = $this->getOrCreateAccount($orgId);
        $balance = (float) ($account['balance'] ?? 0);
        $reserved = (float) ($account['reserved'] ?? 0);

        return [
            'balance'   => $balance,
            'reserved'  => $reserved,
            'available' => $balance - $reserved,
        ];
    }

    /**
     * Active job holds (status = reserved).
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveReservations(string $orgId, int $limit = 50): array
    {
        if ($orgId === '') {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT id, job_id, reservation_reference, estimated_credits, status, created_at, metadata
             FROM job_reservations
             WHERE organisation_id = :org_id AND status = 'reserved'
             ORDER BY created_at DESC
             LIMIT :lim",
            ['org_id' => $orgId, 'lim' => $limit]
        );
    }

    public function findActiveReservationByJobId(string $jobId): array|false
    {
        $jobUuid = $this->normalizeJobUuid($jobId);

        return $this->db->fetch(
            "SELECT * FROM job_reservations
             WHERE job_id = CAST(:job_id AS uuid) AND status = 'reserved'
             LIMIT 1",
            ['job_id' => $jobUuid]
        );
    }

    /**
     * Grant tokens once per ref_type + ref_id (e.g. payment_fulfillment + payment_transaction id).
     */
    public function addTokensIdempotent(
        string $orgId,
        float $amount,
        string $description,
        string $refType,
        string $refId,
        ?string $createdByUserId = null,
        bool $participateInOuterTransaction = false
    ): bool {
        if ($refType !== '' && $refId !== '' && $this->hasLedgerEntry($orgId, $refType, $refId)) {
            return false;
        }

        return $this->addTokens(
            $orgId,
            $amount,
            $description,
            $refType,
            $refId,
            $createdByUserId,
            $participateInOuterTransaction
        );
    }

    public function hasLedgerEntry(string $orgId, string $refType, string $refId): bool
    {
        $refType = trim($refType);
        $refId = trim($refId);
        if ($orgId === '' || $refType === '' || $refId === '') {
            return false;
        }

        $refUuid = $this->parseOptionalUuid($refId);
        if ($refUuid !== null) {
            $row = $this->db->fetch(
                'SELECT id FROM credit_transactions
                 WHERE organisation_id = :org_id AND ref_type = :ref_type AND ref_id = :ref_id
                 LIMIT 1',
                ['org_id' => $orgId, 'ref_type' => $refType, 'ref_id' => $refUuid]
            );

            return $row !== false;
        }

        $rows = $this->db->fetchAll(
            'SELECT metadata FROM credit_transactions
             WHERE organisation_id = :org_id AND ref_type = :ref_type
             ORDER BY created_at DESC
             LIMIT 20',
            ['org_id' => $orgId, 'ref_type' => $refType]
        );
        foreach ($rows as $row) {
            $meta = $this->decodeJson($row['metadata'] ?? null);
            if ((string) ($meta['external_ref'] ?? '') === $refId) {
                return true;
            }
        }

        return false;
    }

    public function addTokens(
        string $orgId,
        float $amount,
        string $description,
        string $refType = '',
        string $refId = '',
        ?string $createdByUserId = null,
        bool $participateInOuterTransaction = false
    ): bool {
        $pdo = $this->db->getPdo();
        $ownsTransaction = !$participateInOuterTransaction;

        if ($participateInOuterTransaction && !$pdo->inTransaction()) {
            throw new \InvalidArgumentException('participateInOuterTransaction requires an active PDO transaction.');
        }

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $account = $this->db->fetch(
                'SELECT * FROM credit_accounts WHERE organisation_id = :org_id FOR UPDATE',
                ['org_id' => $orgId]
            );
            if (!$account) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                $this->getOrCreateAccount($orgId);
                if ($ownsTransaction) {
                    $pdo->beginTransaction();
                }
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

            $metadata = [];
            if ($refType !== '' && $refUuid === null && trim($refId) !== '') {
                $metadata['external_ref'] = trim($refId);
            }

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
                $metadata,
                $createdByUserId
            );

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return true;
        } catch (\Exception $e) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Charge tokens for a completed job (releases hold, deducts actual usage).
     */
    public function captureTokens(string $jobId, float $actualAmount): bool
    {
        if ($actualAmount <= 0) {
            throw new \InvalidArgumentException('Capture amount must be positive');
        }

        if (!$this->finalizeReservation($jobId, $actualAmount)) {
            throw new \RuntimeException('No active reservation found for this job');
        }

        return true;
    }

    public function deductTokens(
        string $orgId,
        float $amount,
        string $description,
        string $refType = '',
        string $refId = '',
        ?string $createdByUserId = null
    ): bool {
        if ($refId !== '' && $this->findActiveReservationByJobId($refId) !== false) {
            throw new \RuntimeException(
                'Job has an active reservation. Use POST /api/v1/capture (or deduct with job_id) to charge on success.'
            );
        }

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

            $available = (float) $account['balance'] - (float) $account['reserved'];
            if ($available < $amount) {
                $pdo->rollBack();
                throw new \RuntimeException('Insufficient available tokens');
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

    public function reserveTokens(string $orgId, float $amount, string $jobId): bool
    {
        $jobUuid = $this->normalizeJobUuid($jobId);

        $existing = $this->findActiveReservationByJobId($jobId);
        if ($existing !== false) {
            throw new \RuntimeException('Job already has an active token reservation');
        }

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
                throw new \RuntimeException('Insufficient available tokens');
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
                throw new \RuntimeException('Insufficient tokens to finalize reservation');
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

    /** @return list<string> */
    public static function usageLedgerTypes(): array
    {
        return ['debit_usage', 'capture', 'debit'];
    }

    /** @return list<string> */
    public static function tokenInLedgerTypes(): array
    {
        return ['credit_topup', 'subscription_credit', 'credit_grant'];
    }

    public static function isUsageLedgerType(string $type): bool
    {
        return in_array($type, self::usageLedgerTypes(), true);
    }

    public static function isReserveLedgerType(string $type): bool
    {
        return $type === 'reserve';
    }

    public static function isReleaseLedgerType(string $type): bool
    {
        return $type === 'release';
    }

    public static function isPendingLedgerType(string $type): bool
    {
        return in_array($type, ['reserve', 'release'], true);
    }

    public static function isTokenInLedgerType(string $type): bool
    {
        return in_array($type, self::tokenInLedgerTypes(), true);
    }

    private static function usageTypesSqlInClause(): string
    {
        return 'IN (' . implode(', ', array_map(static fn (string $t): string => "'" . $t . "'", self::usageLedgerTypes())) . ')';
    }

    /**
     * Calendar month window in the default timezone (same boundaries as usage aggregation).
     *
     * @return array{total: float, label: string, range_start: string, range_end_exclusive: string}
     */
    public function getTokensUsageThisCalendarMonth(string $orgId): array
    {
        $tzName = date_default_timezone_get() ?: 'UTC';
        $tz    = new \DateTimeZone($tzName);
        $ref   = new \DateTimeImmutable('today', $tz);
        $start = $ref->modify('first day of this month')->setTime(0, 0, 0);
        $endEx = $start->modify('+1 month');

        $ctx = [
            'total'                 => 0.0,
            'label'                 => $start->format('F Y'),
            'range_start'           => $start->format('Y-m-d'),
            'range_end_exclusive'   => $endEx->format('Y-m-d'),
        ];

        if ($orgId === '') {
            return $ctx;
        }

        $inClause = self::usageTypesSqlInClause();
        $fromStr  = $start->format('Y-m-d H:i:s');
        $toStr    = $endEx->format('Y-m-d H:i:s');

        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM credit_transactions
             WHERE organisation_id = :org_id
               AND type {$inClause}
               AND created_at >= :from_ts
               AND created_at < :to_ts",
            ['org_id' => $orgId, 'from_ts' => $fromStr, 'to_ts' => $toStr]
        );

        $ctx['total'] = (float) ($row['total'] ?? 0);

        return $ctx;
    }

    /**
     * Daily consumed tokens from the ledger for dashboard charting.
     * Counts {@see deductCredits} (`debit_usage`) and finalized job usage (`capture`).
     * Ignores top-ups, reservations, and releases so the line reflects actual spend.
     *
     * @return array{points: list<array{label: string, val: float}>, caption: string}
     */
    public function getUsageTrendLastDays(string $orgId, int $days = 7): array
    {
        $days = max(1, min(31, $days));
        $tzName = date_default_timezone_get() ?: 'UTC';
        $tz    = new \DateTimeZone($tzName);
        $today = new \DateTimeImmutable('today', $tz);
        $first = $today->modify('-' . ($days - 1) . ' days');
        $after  = $today->modify('+1 day');

        if ($orgId === '') {
            return [
                'points'  => self::buildEmptyTrendPoints($first, $after),
                'caption' => 'Select an organisation to see token usage.',
            ];
        }

        $fromStr = $first->format('Y-m-d H:i:s');
        $toStr   = $after->format('Y-m-d H:i:s');

        $usageIn = self::usageTypesSqlInClause();
        $rows    = $this->db->fetchAll(
            "SELECT DATE(created_at) AS day, COALESCE(SUM(amount), 0) AS used
             FROM credit_transactions
             WHERE organisation_id = :org_id
               AND type {$usageIn}
               AND created_at >= :from_ts
               AND created_at < :to_ts
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            ['org_id' => $orgId, 'from_ts' => $fromStr, 'to_ts' => $toStr]
        );

        $byDay = [];
        foreach ($rows as $row) {
            $raw = (string) ($row['day'] ?? '');
            $dayKey = substr($raw, 0, 10);
            if ($dayKey !== '') {
                $byDay[$dayKey] = (float) ($row['used'] ?? 0);
            }
        }

        $points = [];
        $cursor = $first;
        while ($cursor < $after) {
            $key = $cursor->format('Y-m-d');
            $points[] = [
                'label' => $cursor->format('D'),
                'val'   => (float) ($byDay[$key] ?? 0.0),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $total = 0.0;
        foreach ($points as $p) {
            $total += $p['val'];
        }

        $caption = self::formatUsageTrendCaption($points, $total, $days);

        return ['points' => $points, 'caption' => $caption];
    }

    /**
     * @param list<array{label: string, val: float}> $points
     */
    private static function formatUsageTrendCaption(array $points, float $total, int $days): string
    {
        if ($total <= 0) {
            return 'No usage in the last ' . $days . ' days.';
        }

        $totalStr = self::formatTokensAmount($total);
        $n        = count($points);
        if ($n < 4) {
            return $totalStr . ' tokens used (last ' . $days . ' days)';
        }

        $mid   = (int) ceil($n / 2);
        $early = 0.0;
        $late  = 0.0;
        for ($i = 0; $i < $mid; $i++) {
            $early += $points[$i]['val'];
        }
        for ($i = $mid; $i < $n; $i++) {
            $late += $points[$i]['val'];
        }

        if ($early <= 0 && $late > 0) {
            return $totalStr . ' tokens · usage picked up in the second half';
        }
        if ($early <= 0) {
            return $totalStr . ' tokens used (last ' . $days . ' days)';
        }

        $pct = (($late - $early) / $early) * 100.0;

        return $totalStr . ' tokens · ' . sprintf('%+.1f%%', $pct) . ' recent vs earlier period';
    }

    private static function formatTokensAmount(float $v): string
    {
        $s = number_format($v, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * @return list<array{label: string, val: float}>
     */
    private static function buildEmptyTrendPoints(\DateTimeImmutable $first, \DateTimeImmutable $after): array
    {
        $points = [];
        $cursor = $first;
        while ($cursor < $after) {
            $points[] = ['label' => $cursor->format('D'), 'val' => 0.0];
            $cursor    = $cursor->modify('+1 day');
        }

        return $points;
    }

    private function parseOptionalUuid(string $id): ?string
    {
        $id = trim($id);
        if ($id === '' || !self::isUuidString($id)) {
            return null;
        }
        return $id;
    }

    private function decodeJson(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
