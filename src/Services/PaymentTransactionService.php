<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\ApiClient;

class PaymentTransactionService
{
    private string $clientApiUrl;

    public function __construct()
    {
        $this->clientApiUrl = (string) ($_ENV['CLIENT_API_URL'] ?? '');
    }

    public function createPending(
        string $orgId,
        string $userId,
        string $planId,
        ?string $paymentMethodId,
        string $providerRef,
        float $amount,
        array $payload = [],
        ?string $invoiceId = null
    ): string {
        $resp = ApiClient::post($this->clientApiUrl, '/payment-transactions', [
            'organisation_id' => $orgId,
            'invoice_id' => $invoiceId,
            'payment_method_id' => $paymentMethodId,
            'provider' => 'payfast',
            'merchant_reference' => $providerRef,
            'idempotency_key' => $providerRef,
            'amount' => $amount,
            'currency' => 'ZAR',
            'status' => 'initiated',
            'raw_response' => [
                'user_id' => $userId ?: null,
                'plan_id' => $planId ?: null,
                'payload' => $payload,
            ],
        ]);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        return (string) ($data['id'] ?? '');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|false
     */
    public function createPayfastCheckout(array $payload): array|false
    {
        $resp = ApiClient::post($this->clientApiUrl, '/checkout/payfast', $payload);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        if (!is_array($data) || $data === [] || array_is_list($data) || isset($data['error'])) {
            return false;
        }

        return $data;
    }

    /**
     * @param array<string, string> $payload
     * @return array{http_code: int, message: string, body: array<string, mixed>}
     */
    public function processPayfastItn(array $payload, string $rawBody = '', bool $signatureValid = true): array
    {
        $enriched = $this->enrichItnPayload($payload);
        $resp = ApiClient::post(
            $this->clientApiUrl,
            '/checkout/payfast/itn',
            $this->buildItnRequestBody($enriched, $rawBody, $signatureValid)
        );
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        $httpCode = (int) ($data['_http_code'] ?? 0);
        unset($data['_http_code']);

        if ($httpCode === 0) {
            $httpCode = (int) ($data['http_code'] ?? $data['status_code'] ?? 0);
        }
        if ($httpCode === 0) {
            $httpCode = isset($data['error']) ? 422 : 200;
        }

        $message = (string) ($data['message'] ?? $data['error'] ?? 'OK');
        if ($httpCode >= 400 && $message === 'OK') {
            $message = 'ITN processing failed';
        }

        $outboxEventId = (string) ($data['outbox_event_id'] ?? '');
        if ($httpCode < 400 && $outboxEventId !== '' && empty($data['outbox_process'])) {
            $data['outbox_process'] = $this->processPaymentOutboxEvent($outboxEventId);
        }

        return [
            'http_code' => $httpCode,
            'message' => $message,
            'body' => is_array($data) ? $data : [],
        ];
    }

    /**
     * @param array<string, string> $payload
     */
    public function recordPayfastItn(
        array $payload,
        string $rawBody,
        string $status,
        string $message = '',
        ?bool $signatureValid = null
    ): void {
        $enriched = $this->enrichItnPayload($payload);
        $merchantReference = trim((string) ($enriched['merchant_reference'] ?? $enriched['m_payment_id'] ?? ''));

        ApiClient::post($this->clientApiUrl, '/payfast-itn-logs', [
            'received_at' => date('c'),
            'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'raw_body' => $rawBody,
            'parsed_payload' => $enriched,
            'merchant_reference' => $merchantReference !== '' ? $merchantReference : null,
            'pf_payment_id' => $enriched['pf_payment_id'] ?? null,
            'payment_status' => $enriched['payment_status'] ?? null,
            'amount_gross' => $enriched['amount_gross'] ?? null,
            'signature_received' => $enriched['signature'] ?? null,
            'signature_valid' => $signatureValid,
            'processing_status' => $status,
            'processing_message' => $message !== '' ? $message : null,
            // Backward-compatible aliases for older client-api handlers.
            'payload' => $enriched,
            'status' => $status,
            'message' => $message,
        ]);
    }

    /**
     * Ensure PayFast ITN fields expected by client-api are present.
     *
     * @param array<string, string> $payload
     * @return array<string, string>
     */
    public function enrichItnPayload(array $payload): array
    {
        $merchantReference = trim((string) ($payload['merchant_reference'] ?? $payload['m_payment_id'] ?? ''));
        if ($merchantReference !== '') {
            $payload['merchant_reference'] = $merchantReference;
            $payload['m_payment_id'] = (string) ($payload['m_payment_id'] ?? $merchantReference);
        }

        $paymentTransactionId = trim((string) ($payload['payment_transaction_id'] ?? $payload['custom_str5'] ?? ''));
        if ($paymentTransactionId === '' && $merchantReference !== '') {
            try {
                $tx = $this->findByProviderRef($merchantReference);
                if (is_array($tx) && !empty($tx['id'])) {
                    $paymentTransactionId = (string) $tx['id'];
                }
            } catch (\Throwable $e) {
                $paymentTransactionId = '';
            }
        }
        if ($paymentTransactionId !== '') {
            $payload['payment_transaction_id'] = $paymentTransactionId;
            if (trim((string) ($payload['custom_str5'] ?? '')) === '') {
                $payload['custom_str5'] = $paymentTransactionId;
            }
        }

        if ($merchantReference !== '' && trim((string) ($payload['amount_gross'] ?? '')) === '') {
            try {
                $tx = $this->findByProviderRef($merchantReference);
                if (is_array($tx) && array_key_exists('amount', $tx)) {
                    $payload['amount_gross'] = number_format((float) $tx['amount'], 2, '.', '');
                }
            } catch (\Throwable $e) {
                // Leave amount_gross empty; client-api will return validation details.
            }
        }

        return $payload;
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, mixed>
     */
    private function buildItnRequestBody(array $payload, string $rawBody, bool $signatureValid): array
    {
        return [
            'merchant_reference' => (string) ($payload['merchant_reference'] ?? $payload['m_payment_id'] ?? ''),
            'm_payment_id' => (string) ($payload['m_payment_id'] ?? $payload['merchant_reference'] ?? ''),
            'payment_transaction_id' => (string) ($payload['payment_transaction_id'] ?? $payload['custom_str5'] ?? ''),
            'pf_payment_id' => (string) ($payload['pf_payment_id'] ?? ''),
            'payment_status' => (string) ($payload['payment_status'] ?? ''),
            'amount_gross' => (string) ($payload['amount_gross'] ?? ''),
            'signature' => (string) ($payload['signature'] ?? ''),
            'payload' => $payload,
            'raw_body' => $rawBody,
            'signature_valid' => $signatureValid,
        ];
    }

    /**
     * @param array<string, string> $payload
     * @return list<string>
     */
    public static function missingItnFields(array $payload): array
    {
        $required = [
            'merchant_reference' => ['merchant_reference', 'm_payment_id'],
            'm_payment_id' => ['m_payment_id', 'merchant_reference'],
            'payment_transaction_id' => ['payment_transaction_id', 'custom_str5'],
            'pf_payment_id' => ['pf_payment_id'],
            'payment_status' => ['payment_status'],
            'amount_gross' => ['amount_gross'],
        ];

        $missing = [];
        foreach ($required as $label => $keys) {
            $found = false;
            foreach ($keys as $key) {
                if (trim((string) ($payload[$key] ?? '')) !== '') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * Row lock for ITN idempotency (call inside an open transaction).
     */
    public function fetchByMerchantReferenceForUpdate(string $providerRef): array|false
    {
        $row = $this->findByProviderRef($providerRef);
        return $row === false ? false : $row;
    }

    public function findByProviderRef(string $providerRef): array|false
    {
        $resp = ApiClient::get($this->clientApiUrl, '/payment-transactions/by-reference/' . urlencode($providerRef));
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        if (!is_array($data) || $data === [] || array_is_list($data)) {
            return false;
        }
        return $data;
    }

    public function markPaid(string $id, array $payload): int
    {
        return $this->markStatus($id, 'successful', $payload);
    }

    public function markCancelled(string $id, array $payload): int
    {
        return $this->markStatus($id, 'cancelled', $payload);
    }

    public function markFailed(string $id, array $payload): int
    {
        return $this->markStatus($id, 'failed', $payload);
    }

    public function markActivated(string $id): int
    {
        return $this->markStatus($id, 'successful', []);
    }

    /**
     * Mark successful after activation, merge ITN into raw_response, link invoice.
     *
     * @param array<string, mixed> $itnPayload
     */
    public function markSuccessfulWithItn(string $id, array $itnPayload, ?string $invoiceId = null): int
    {
        $resp = ApiClient::post($this->clientApiUrl, '/payment-transactions/' . urlencode($id) . '/mark-successful-itn', [
            'itn_payload' => $itnPayload,
            'invoice_id' => $invoiceId,
        ]);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        return (int) ($data['updated'] ?? 0);
    }

    /**
     * Process the payment outbox event that activates subscriptions, grants credits,
     * and marks invoice credits as granted.
     *
     * @return array<string, mixed>
     */
    public function processPaymentOutboxEvent(string $eventId): array
    {
        if (trim($eventId) === '') {
            return [];
        }

        $resp = ApiClient::post($this->clientApiUrl, '/payment-outbox-events/' . urlencode($eventId) . '/process', []);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;

        return is_array($data) && !array_is_list($data) ? $data : [];
    }

    public function getRecentForOrganisation(string $orgId, int $limit = 20, int $offset = 0): array
    {
        $resp = ApiClient::get($this->clientApiUrl, '/payment-transactions/organisation/' . urlencode($orgId), [
            'limit' => $limit,
            'offset' => $offset,
        ]);
        if (isset($resp['data']) && is_array($resp['data'])) {
            return $resp['data'];
        }
        return array_is_list($resp) ? $resp : [];
    }

    private function markStatus(string $id, string $status, array $payload): int
    {
        $resp = ApiClient::post($this->clientApiUrl, '/payment-transactions/' . urlencode($id) . '/status', [
            'status' => $status,
            'raw_response' => $payload,
        ]);
        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : $resp;
        return (int) ($data['updated'] ?? 0);
    }
}
