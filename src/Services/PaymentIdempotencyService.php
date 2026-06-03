<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class PaymentIdempotencyService
{
    public const REF_TYPE_PAYMENT_FULFILLMENT = 'payment_fulfillment';

    private string $clientApiUrl;

    public function __construct()
    {
        $this->clientApiUrl = (string) ($_ENV['CLIENT_API_URL'] ?? '');
    }

    /** @return array<string, mixed>|list<mixed> */
    private function unwrap(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    public function isPayfastPaymentAlreadyFulfilled(string $pfPaymentId, string $merchantReference): bool
    {
        if (trim($pfPaymentId) === '') {
            return false;
        }

        $resp = ApiClient::get($this->clientApiUrl, '/payment-idempotency/payfast/fulfilled', [
            'pf_payment_id' => $pfPaymentId,
            'merchant_reference' => $merchantReference,
        ]);
        $data = $this->unwrap($resp);

        return (bool) ($data['fulfilled'] ?? $data['exists'] ?? false);
    }

    public function tryClaimInvoiceTokenGrant(string $invoiceId): bool
    {
        if (trim($invoiceId) === '') {
            return false;
        }

        $resp = ApiClient::post(
            $this->clientApiUrl,
            '/payment-idempotency/invoices/' . urlencode($invoiceId) . '/claim-token-grant',
            []
        );
        $data = $this->unwrap($resp);

        return (bool) ($data['claimed'] ?? false);
    }

    public function isInvoiceTokenGrantClaimed(string $invoiceId): bool
    {
        if (trim($invoiceId) === '') {
            return false;
        }

        $resp = ApiClient::get(
            $this->clientApiUrl,
            '/payment-idempotency/invoices/' . urlencode($invoiceId) . '/token-grant'
        );
        $data = $this->unwrap($resp);

        return (bool) ($data['claimed'] ?? $data['credits_granted'] ?? false);
    }

    public function findReusableCheckoutTransaction(string $orgId, string $idempotencyKey): array|false
    {
        if ($orgId === '' || trim($idempotencyKey) === '') {
            return false;
        }

        $resp = ApiClient::get($this->clientApiUrl, '/payment-idempotency/checkout', [
            'organisation_id' => $orgId,
            'idempotency_key' => $idempotencyKey,
        ]);
        $data = $this->unwrap($resp);

        if (!is_array($data) || $data === [] || array_is_list($data)) {
            return false;
        }

        return $data;
    }
}
