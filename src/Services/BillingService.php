<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class BillingService
{
    private string $clientApiUrl;

    public function __construct()
    {
        $this->clientApiUrl = (string) ($_ENV['CLIENT_API_URL'] ?? '');
    }

    public function getInvoices(string $orgId): array
    {
        $resp = ApiClient::get($this->clientApiUrl, '/invoices/organisation/' . urlencode($orgId));
        if (isset($resp['data']) && is_array($resp['data'])) {
            return $resp['data'];
        }
        return array_is_list($resp) ? $resp : [];
    }

    public function getRecentInvoices(string $orgId, int $limit = 5): array
    {
        return array_slice($this->getInvoices($orgId), 0, max(0, $limit));
    }

    public function getInvoicesPaged(string $orgId, int $limit = 20, int $offset = 0): array
    {
        $resp = ApiClient::get($this->clientApiUrl, '/invoices/organisation/' . urlencode($orgId) . '/paged', [
            'limit' => $limit,
            'offset' => $offset,
        ]);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [];
        $count = (int) ($data['count'] ?? 0);
        if ($rows === [] && $count === 0) {
            $all = $this->getInvoices($orgId);
            return [
                'rows' => array_slice($all, max(0, $offset), max(0, $limit)),
                'count' => count($all),
            ];
        }

        return [
            'rows' => isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [],
            'count' => (int) ($data['count'] ?? 0),
        ];
    }

    public function getInvoice(string $id): array|false
    {
        $resp = ApiClient::get($this->clientApiUrl, '/invoices/' . urlencode($id));
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        if (!is_array($data) || $data === [] || array_is_list($data)) {
            return false;
        }
        return $data;
    }

    public function createInvoice(string $orgId, array $items): string
    {
        $resp = ApiClient::post($this->clientApiUrl, '/invoices', [
            'organisation_id' => $orgId,
            'items' => $items,
        ]);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        return (string) ($data['id'] ?? '');
    }

    public function markPaid(string $invoiceId, string $paymentTransactionId = ''): int
    {
        $resp = ApiClient::post($this->clientApiUrl, '/invoices/' . urlencode($invoiceId) . '/mark-paid', [
            'payment_transaction_id' => $paymentTransactionId !== '' ? $paymentTransactionId : null,
        ]);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        return (int) ($data['updated'] ?? 0);
    }

    /**
     * Marks subscription credits as allocated for this invoice (when columns exist).
     */
    public function markCreditsGrantedForInvoice(string $invoiceId, float $creditsAmount): void
    {
        ApiClient::post($this->clientApiUrl, '/invoices/' . urlencode($invoiceId) . '/credits-granted', [
            'credits_granted_amount' => $creditsAmount,
        ]);
    }
}
