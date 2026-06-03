<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class TokenService
{
    private string $operationsApiUrl;

    public function __construct()
    {
        $this->operationsApiUrl = (string) ($_ENV['OPERATIONS_API_URL'] ?? '');
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function unwrap(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        return $response;
    }

    /** @param array<string, mixed>|list<mixed> $response */
    private function postSucceeded(array $response, array $truthyKeys, array $statusValues = []): bool
    {
        $data = $this->unwrap($response);
        if (!is_array($data) || array_is_list($data)) {
            return false;
        }
        foreach ($truthyKeys as $key) {
            if (!empty($data[$key])) {
                return true;
            }
        }
        $status = strtolower((string) ($data['status'] ?? ''));
        return $status !== '' && in_array($status, $statusValues, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unwrapList(array $response): array
    {
        $data = $this->unwrap($response);
        return array_is_list($data) ? $data : [];
    }

    /**
     * @return array{balance: float, reserved: float, available: float}
     */
    public function getOrCreateAccount(string $orgId): array
    {
        $snapshot = $this->getAccountSnapshot($orgId);
        return [
            'balance' => $snapshot['balance'],
            'reserved' => $snapshot['reserved'],
            'available' => $snapshot['available'],
        ];
    }

    public function getBalance(string $orgId): float
    {
        return $this->getAccountSnapshot($orgId)['balance'];
    }

    public function getAvailableBalance(string $orgId): float
    {
        return $this->getAccountSnapshot($orgId)['available'];
    }

    /**
     * @return array{balance: float, reserved: float, available: float}
     */
    public function getAccountSnapshot(string $orgId): array
    {
        $resp = ApiClient::get($this->operationsApiUrl, '/credits/' . urlencode($orgId));
        $data = $this->unwrap($resp);

        $balance = (float) ($data['balance'] ?? 0);
        $reserved = (float) ($data['reserved'] ?? 0);
        $available = (float) ($data['available'] ?? ($balance - $reserved));

        return [
            'balance' => $balance,
            'reserved' => $reserved,
            'available' => $available,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getActiveReservations(string $orgId, int $limit = 50): array
    {
        return $this->unwrapList(ApiClient::get(
            $this->operationsApiUrl,
            '/credits/' . urlencode($orgId) . '/reservations',
            ['status' => 'reserved', 'limit' => $limit]
        ));
    }

    public function captureTokensForJob(string $orgId, string $jobId, float $amount, string $description = ''): bool
    {
        return $this->finalizeReservation($jobId, $amount);
    }

    public function addTokensIdempotent(
        string $orgId,
        float $amount,
        string $description,
        string $refType,
        string $refId,
        ?string $createdByUserId = null,
        bool $participateInOuterTransaction = false
    ): bool {
        $resp = ApiClient::post($this->operationsApiUrl, '/credits/grant', [
            'organisation_id' => $orgId,
            'credits_to_grant' => $amount,
            'description' => $description,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_by_user_id' => $createdByUserId,
            'source' => $refType !== '' ? $refType : 'business_layer',
            'reference' => $refId !== '' ? $refId : null,
        ]);
        $data = $this->unwrap($resp);
        return !isset($data['error']) && ((float) ($data['granted'] ?? 0) > 0 || (bool) ($data['ok'] ?? $data['created'] ?? false));
    }

    public function hasLedgerEntry(string $orgId, string $refType, string $refId): bool
    {
        $resp = ApiClient::get($this->operationsApiUrl, '/credits/' . urlencode($orgId) . '/ledger/exists', [
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);
        $data = $this->unwrap($resp);
        return (bool) ($data['exists'] ?? false);
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
        $resp = ApiClient::post($this->operationsApiUrl, '/credits/grant', [
            'organisation_id' => $orgId,
            'credits_to_grant' => $amount,
            'description' => $description,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_by_user_id' => $createdByUserId,
            'source' => $refType !== '' ? $refType : 'business_layer',
            'reference' => $refId !== '' ? $refId : null,
        ]);
        $data = $this->unwrap($resp);
        return !isset($data['error']) && ((float) ($data['granted'] ?? 0) > 0 || (bool) ($data['ok'] ?? $data['created'] ?? false));
    }

    public function deductTokens(
        string $orgId,
        float $amount,
        string $description,
        string $refType = '',
        string $refId = '',
        ?string $createdByUserId = null
    ): bool {
        return $this->recordUsage($orgId, $amount, $description, $refType, $refId, $createdByUserId);
    }

    public function recordUsage(
        string $orgId,
        float $amount,
        string $description,
        string $refType = 'usage',
        string $refId = '',
        ?string $createdByUserId = null,
        array $metadata = []
    ): bool {
        if ($refId === '' || !self::isUuid($refId)) {
            $refId = self::uuidV4();
        }

        $resp = ApiClient::post($this->operationsApiUrl, '/credits/debit-usage', [
            'organisation_id' => $orgId,
            'amount' => $amount,
            'description' => $description,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_by_user_id' => $createdByUserId,
            'metadata' => $metadata,
        ]);
        return $this->postSucceeded($resp, ['ok'], ['debit_usage']);
    }

    public static function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($bytes), 4)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRecentUsageTransactions(string $orgId, int $limit = 10): array
    {
        $rows = $this->getTransactionHistory($orgId, max($limit * 3, 30));
        $usage = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!self::isUsageLedgerType((string) ($row['type'] ?? ''))) {
                continue;
            }
            $usage[] = $this->enrichUsageTransactionRow($row);
            if (count($usage) >= $limit) {
                break;
            }
        }

        return $usage;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichUsageTransactionRow(array $row): array
    {
        $meta = $row['metadata'] ?? null;
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($meta)) {
            $meta = [];
        }

        if (empty($row['product_name'])) {
            $row['product_name'] = (string) (
                $meta['dashboard_label']
                ?? $meta['product_name']
                ?? $row['description']
                ?? ''
            );
        }

        $row['tokens_used'] = (float) ($row['tokens_used'] ?? $row['amount'] ?? 0);

        return $row;
    }

    public function reserveTokens(string $orgId, float $amount, string $jobId): bool
    {
        $resp = ApiClient::post($this->operationsApiUrl, '/credits/reserve', [
            'organisation_id' => $orgId,
            'amount' => $amount,
            'job_id' => $jobId,
        ]);
        return $this->postSucceeded($resp, ['ok', 'reserved'], ['reserved']);
    }

    public function releaseReservation(string $jobId): bool
    {
        $resp = ApiClient::post($this->operationsApiUrl, '/credits/release', [
            'job_id' => $jobId,
        ]);
        return $this->postSucceeded($resp, ['ok', 'released'], ['released']);
    }

    public function finalizeReservation(string $jobId, float $actualAmount): bool
    {
        $resp = ApiClient::post($this->operationsApiUrl, '/credits/finalize', [
            'job_id' => $jobId,
            'actual_amount' => $actualAmount,
        ]);
        return $this->postSucceeded($resp, ['ok', 'captured'], ['finalized', 'captured']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTransactionHistory(string $orgId, int $limit = 50): array
    {
        return $this->unwrapList(ApiClient::get(
            $this->operationsApiUrl,
            '/credits/' . urlencode($orgId) . '/transactions',
            ['limit' => $limit]
        ));
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

    public static function isTokenInLedgerType(string $type): bool
    {
        return in_array($type, self::tokenInLedgerTypes(), true);
    }

    public static function isReservationLockType(string $type): bool
    {
        return $type === 'reserve';
    }

    public static function isReservationReleaseType(string $type): bool
    {
        return $type === 'release';
    }

    public static function ledgerTypeLabel(string $type): string
    {
        return match ($type) {
            'reserve' => 'Pending (reserved)',
            'release' => 'Released',
            'capture' => 'Captured',
            'debit_usage', 'debit' => 'Usage',
            'credit_topup', 'subscription_credit', 'credit_grant' => 'Grant',
            default => $type !== '' ? $type : '—',
        };
    }

    /** @return 'active'|'revoked'|'pending' */
    public static function ledgerBadgeClass(string $type): string
    {
        if (self::isTokenInLedgerType($type)) {
            return 'active';
        }
        if (self::isUsageLedgerType($type)) {
            return 'revoked';
        }
        return 'pending';
    }

    /**
     * @return array{total: float, label: string, range_start: string, range_end_exclusive: string}
     */
    public function getTokensUsageThisCalendarMonth(string $orgId): array
    {
        $tzName = date_default_timezone_get() ?: 'UTC';
        $tz = new \DateTimeZone($tzName);
        $ref = new \DateTimeImmutable('today', $tz);
        $start = $ref->modify('first day of this month')->setTime(0, 0, 0);
        $endEx = $start->modify('+1 month');

        $resp = ApiClient::get(
            $this->operationsApiUrl,
            '/credits/' . urlencode($orgId) . '/usage/month',
            ['start' => $start->format('Y-m-d'), 'end_exclusive' => $endEx->format('Y-m-d')]
        );
        $data = $this->unwrap($resp);

        return [
            'total' => (float) ($data['total'] ?? 0),
            'label' => (string) ($data['label'] ?? $start->format('F Y')),
            'range_start' => (string) ($data['range_start'] ?? $start->format('Y-m-d')),
            'range_end_exclusive' => (string) ($data['range_end_exclusive'] ?? $endEx->format('Y-m-d')),
        ];
    }

    /**
     * @return array{points: list<array{date: string, value: float}>, caption: string}
     */
    public function getUsageTrendLastDays(string $orgId, int $days = 7): array
    {
        $resp = ApiClient::get(
            $this->operationsApiUrl,
            '/usage/' . urlencode($orgId) . '/trend',
            ['days' => $days]
        );
        $data = $this->unwrap($resp);

        // Accept both API shapes:
        // 1) { "points": [ ... ], "caption": "..." }
        // 2) [ { "date": "...", "tokens_used": 410 }, ... ]
        $rows = [];
        if (isset($data['points']) && is_array($data['points'])) {
            $rows = $data['points'];
        } elseif (array_is_list($data)) {
            $rows = $data;
        }

        $points = $this->fillTrendPoints($this->normalizeTrendRows($rows), $days);

        return [
            'points' => $points,
            'caption' => (string) ($data['caption'] ?? $this->formatUsageTrendCaption($points, $days)),
        ];
    }

    /**
     * @param list<int> $dayOptions
     * @return array<string, array{points: list<array<string, mixed>>, caption: string}>
     */
    public function getUsageTrendSeries(string $orgId, array $dayOptions = [7, 14, 30]): array
    {
        $series = [];
        foreach ($dayOptions as $days) {
            $days = max(1, min(31, (int) $days));
            $series[(string) $days] = $this->getUsageTrendLastDays($orgId, $days);
        }

        return $series;
    }

    /**
     * Per-product daily usage for chart filters.
     *
     * @return array<string, array{slug: string, name: string, points: list<array<string, mixed>>}>
     */
    public function getUsageTrendByProduct(string $orgId, int $days = 7): array
    {
        $days = max(1, min(31, $days));
        if ($orgId === '') {
            return [];
        }

        $resp = ApiClient::get(
            $this->operationsApiUrl,
            '/usage/' . urlencode($orgId) . '/trend/by-product',
            ['days' => $days]
        );
        $data = $this->unwrap($resp);

        $items = [];
        if (isset($data['products']) && is_array($data['products'])) {
            $items = $data['products'];
        } elseif (array_is_list($data)) {
            $items = $data;
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = (string) ($item['product_slug'] ?? $item['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $rows = [];
            if (isset($item['points']) && is_array($item['points'])) {
                $rows = $item['points'];
            } elseif (isset($item['series']) && is_array($item['series'])) {
                $rows = $item['series'];
            }
            $out[$slug] = [
                'slug' => $slug,
                'name' => (string) ($item['product_name'] ?? $item['name'] ?? $slug),
                'points' => $this->fillTrendPoints($this->normalizeTrendRows($rows), $days),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{date: string, value: float, tokens_used: float}>
     */
    private function normalizeTrendRows(array $rows): array
    {
        $points = [];
        foreach ($rows as $point) {
            if (!is_array($point)) {
                continue;
            }

            $date = (string) ($point['date'] ?? '');
            if ($date === '' && isset($point['usage_date'])) {
                $date = (string) $point['usage_date'];
            }
            if ($date === '' && isset($point['day'])) {
                $date = (string) $point['day'];
            }
            if ($date === '' && isset($point['period'])) {
                $date = (string) $point['period'];
            }
            if ($date !== '') {
                $date = substr($date, 0, 10);
            }

            $tokensUsedRaw = $point['tokens_used'] ?? ($point['credits_used'] ?? ($point['value'] ?? ($point['val'] ?? 0)));
            if (is_string($tokensUsedRaw)) {
                $tokensUsedRaw = preg_replace('/[^0-9.\-]/', '', $tokensUsedRaw) ?? '0';
            }
            $value = (float) $tokensUsedRaw;

            $points[] = [
                'date' => $date,
                'value' => $value,
                'tokens_used' => $value,
            ];
        }

        return $points;
    }

    /**
     * @param list<array{date: string, value: float, tokens_used?: float}> $points
     * @return list<array{date: string, value: float, tokens_used: float}>
     */
    private function fillTrendPoints(array $points, int $days): array
    {
        $days = max(1, min(31, $days));
        $tzName = date_default_timezone_get() ?: 'UTC';
        $tz = new \DateTimeZone($tzName);
        $today = new \DateTimeImmutable('today', $tz);
        $first = $today->modify('-' . ($days - 1) . ' days');
        $after = $today->modify('+1 day');

        $byDay = [];
        foreach ($points as $point) {
            $key = substr((string) ($point['date'] ?? ''), 0, 10);
            if ($key === '') {
                continue;
            }
            $byDay[$key] = (float) ($point['tokens_used'] ?? $point['value'] ?? 0);
        }

        $filled = [];
        $cursor = $first;
        while ($cursor < $after) {
            $key = $cursor->format('Y-m-d');
            $value = (float) ($byDay[$key] ?? 0.0);
            $filled[] = [
                'date' => $key,
                'value' => $value,
                'tokens_used' => $value,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $filled;
    }

    /**
     * @param list<array{date: string, value: float, tokens_used?: float}> $points
     */
    private function formatUsageTrendCaption(array $points, int $days): string
    {
        $total = 0.0;
        foreach ($points as $point) {
            $total += (float) ($point['tokens_used'] ?? $point['value'] ?? 0);
        }

        if ($total <= 0) {
            return 'No usage in the last ' . $days . ' days.';
        }

        $totalStr = rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.');

        return $totalStr . ' tokens used (last ' . $days . ' days)';
    }
}
