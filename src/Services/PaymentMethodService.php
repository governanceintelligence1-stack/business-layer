<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class PaymentMethodService
{
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

    /** @return list<array<string, mixed>> */
    private function listFromResponse(array $response): array
    {
        $data = $this->unwrap($response);
        return array_is_list($data) ? $data : [];
    }

    /** @return array<string, mixed>|false */
    private function itemFromResponse(array $response): array|false
    {
        $data = $this->unwrap($response);
        if (!is_array($data) || $data === [] || array_is_list($data)) {
            return false;
        }

        return $data;
    }

    public function getForOrganisation(string $orgId): array
    {
        return $this->listFromResponse(ApiClient::get(
            $this->clientApiUrl,
            '/payment-methods/organisation/' . urlencode($orgId)
        ));
    }

    public function findById(string $id, string $orgId): array|false
    {
        foreach ($this->getForOrganisation($orgId) as $method) {
            if ((string) ($method['id'] ?? '') === $id) {
                return $method;
            }
        }

        return false;
    }

    public function findCardDetailsForPayment(string $id, string $orgId): array
    {
        $method = $this->findById($id, $orgId);
        return $method === false ? [] : $method;
    }

    public function saveCard(
        string $orgId,
        string $userId,
        string $brand,
        string $last4,
        string $expiryMonth,
        string $expiryYear,
        string $cardholderName,
        bool $setDefault = false,
        string $cardNumber = ''
    ): string {
        $resp = ApiClient::post($this->clientApiUrl, '/payment-methods', [
            'organisation_id' => $orgId,
            'created_by' => $userId !== '' ? $userId : null,
            'provider' => 'payfast',
            'type' => 'card',
            'brand' => $brand ?: 'Card',
            'last4' => $last4,
            'expiry_month' => $expiryMonth ?: null,
            'expiry_year' => $expiryYear ?: null,
            'cardholder_name' => $cardholderName ?: null,
            'is_default' => $setDefault,
        ]);
        $data = $this->unwrap($resp);

        return (string) ($data['id'] ?? '');
    }

    public function setDefault(string $id, string $orgId): int
    {
        $resp = ApiClient::post($this->clientApiUrl, '/payment-methods/' . urlencode($id) . '/default', [
            'organisation_id' => $orgId,
        ]);
        $data = $this->unwrap($resp);

        return (int) ($data['updated'] ?? 0);
    }
}
