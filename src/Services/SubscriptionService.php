<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class SubscriptionService
{
    private string $operationsApiUrl;

    public function __construct()
    {
        $this->operationsApiUrl = (string) ($_ENV['OPERATIONS_API_URL'] ?? '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listFromResponse(array $response): array
    {
        if ($this->isErrorResponse($response)) {
            return [];
        }

        unset($response['_http_code']);

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return array_is_list($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function itemFromResponse(array $response): array|false
    {
        if ($this->isErrorResponse($response)) {
            return false;
        }

        unset($response['_http_code']);

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        if ($response === [] || array_is_list($response)) {
            return false;
        }

        return $response;
    }

    /** @param array<string, mixed> $response */
    private function isErrorResponse(array $response): bool
    {
        if (isset($response['error'])) {
            return true;
        }

        $httpCode = (int) ($response['_http_code'] ?? 0);
        return $httpCode >= 400;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeSubscriptionRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $this->normalizeSubscriptionRow($row);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeSubscriptionRow(array $row): array
    {
        unset($row['_http_code']);

        $status = strtolower((string) ($row['status'] ?? $row['subscription_status'] ?? ''));
        $started = (string) ($row['started_at'] ?? $row['created_at'] ?? $row['current_period_start'] ?? '');
        $ended = (string) ($row['ended_at'] ?? $row['cancelled_at'] ?? '');

        if ($ended === '' && in_array($status, ['cancelled', 'canceled', 'expired', 'ended'], true)) {
            $ended = (string) ($row['current_period_end'] ?? '');
        }

        return array_merge($row, [
            'id' => (string) ($row['id'] ?? $row['subscription_id'] ?? ''),
            'status' => $status,
            'started_at' => $started,
            'ended_at' => $ended,
            'created_at' => $started !== '' ? $started : (string) ($row['created_at'] ?? ''),
            'cancelled_at' => $ended !== '' ? $ended : (string) ($row['cancelled_at'] ?? ''),
        ]);
    }

    /**
     * Full subscription history for an organisation (Status, Started, Ended).
     *
     * @return list<array<string, mixed>>
     */
    public function getHistoryForOrganisation(string $orgId): array
    {
        if ($orgId === '') {
            return [];
        }

        $history = $this->normalizeSubscriptionRows($this->listFromResponse(
            ApiClient::get(
                $this->operationsApiUrl,
                '/subscriptions/organisation/' . urlencode($orgId) . '/history'
            )
        ));

        if ($history !== []) {
            return $history;
        }

        $list = $this->normalizeSubscriptionRows($this->listFromResponse(
            ApiClient::get(
                $this->operationsApiUrl,
                '/subscriptions/organisation/' . urlencode($orgId)
            )
        ));

        if ($list !== []) {
            return $list;
        }

        $active = $this->getActive($orgId);
        return is_array($active) ? [$active] : [];
    }

    public function getForOrganisation(string $orgId): array
    {
        return $this->getHistoryForOrganisation($orgId);
    }

    public function getActive(string $orgId): array|false
    {
        $active = $this->itemFromResponse(
            ApiClient::get($this->operationsApiUrl, '/subscriptions/organisation/' . urlencode($orgId) . '/active')
        );

        return is_array($active) ? $this->normalizeSubscriptionRow($active) : false;
    }

    public function create(string $orgId, string $planId, string $billingCycle = 'monthly'): string
    {
        $resp = ApiClient::post($this->operationsApiUrl, '/subscriptions', [
            'organisation_id' => $orgId,
            'plan_id' => $planId,
            'billing_cycle' => $billingCycle,
        ]);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        return (string) ($data['id'] ?? '');
    }

    public function cancel(string $subscriptionId): int
    {
        $resp = ApiClient::post($this->operationsApiUrl, '/subscriptions/' . urlencode($subscriptionId) . '/cancel', []);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        return (int) ($data['updated'] ?? 0);
    }

    public function isActive(string $orgId): bool
    {
        return $this->getActive($orgId) !== false;
    }

    public function getCurrentPlan(string $orgId): array|false
    {
        return $this->getActive($orgId);
    }
}
