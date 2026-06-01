<?php
declare(strict_types=1);

namespace GI\Services;

/**
 * @deprecated Use TokenService. Kept for backward compatibility with older integrations.
 */
class CreditService extends TokenService
{
    public function addCredits(
        string $orgId,
        float $amount,
        string $description,
        string $refType = '',
        string $refId = '',
        ?string $createdByUserId = null,
        bool $participateInOuterTransaction = false
    ): bool {
        return $this->addTokens($orgId, $amount, $description, $refType, $refId, $createdByUserId, $participateInOuterTransaction);
    }

    public function deductCredits(
        string $orgId,
        float $amount,
        string $description,
        string $refType = '',
        string $refId = ''
    ): bool {
        return $this->deductTokens($orgId, $amount, $description, $refType, $refId);
    }

    public function reserveCredits(string $orgId, float $amount, string $jobId): bool
    {
        return $this->reserveTokens($orgId, $amount, $jobId);
    }

    public function getCreditsUsageThisCalendarMonth(string $orgId): array
    {
        return $this->getTokensUsageThisCalendarMonth($orgId);
    }

    public static function creditInLedgerTypes(): array
    {
        return self::tokenInLedgerTypes();
    }

    public static function isCreditInLedgerType(string $type): bool
    {
        return self::isTokenInLedgerType($type);
    }
}
