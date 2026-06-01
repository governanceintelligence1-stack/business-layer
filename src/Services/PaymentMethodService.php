<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class PaymentMethodService
{
    private const ENCODED_DETAILS_PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';

    private DB $db;
    private ?array $columns = null;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function getForOrganisation(string $orgId): array
    {
        $methods = $this->db->fetchAll(
            "SELECT * FROM payment_methods
             WHERE organisation_id = :org_id AND status = 'active'
             ORDER BY is_default DESC, created_at DESC",
            ['org_id' => $orgId]
        );

        return array_map(fn(array $method): array => $this->normaliseCardMethod($method), $methods);
    }

    public function findById(string $id, string $orgId): array|false
    {
        $method = $this->db->fetch(
            "SELECT * FROM payment_methods
             WHERE id = :id AND organisation_id = :org_id AND status = 'active'",
            ['id' => $id, 'org_id' => $orgId]
        );

        return is_array($method) ? $this->normaliseCardMethod($method) : false;
    }

    public function findCardDetailsForPayment(string $id, string $orgId): array
    {
        $method = $this->findById($id, $orgId);
        if (!$method) {
            return [];
        }

        return $this->decodeCardDetailsFromMethod($method);
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
        if ($setDefault) {
            $this->clearDefault($orgId);
        }

        $metadata = [
            'cardholder_name' => $cardholderName ?: null,
            'brand' => $brand ?: 'Card',
            'last4' => $last4,
            'expiry_month' => $expiryMonth ?: null,
            'expiry_year' => $expiryYear ?: null,
        ];
        $encodedDetails = $this->encodeCardDetails([
            'cardholder_name' => $cardholderName,
            'card_number'     => $cardNumber,
            'expiry_month'    => $expiryMonth,
            'expiry_year'     => $expiryYear,
        ]);
        if ($encodedDetails !== '') {
            $metadata['encoded_details'] = $encodedDetails;
        }

        $data = [
            'organisation_id' => $orgId,
            'provider'        => 'payfast',
            'metadata'        => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'is_default'      => $setDefault ? 'true' : 'false',
            'status'          => 'active',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        $optional = [
            'provider_customer_id' => $userId !== '' ? $userId : null,
            'provider_payment_method_id' => null,
            'type' => 'card',
            'method_type' => 'card',
            'brand' => $brand ?: 'Card',
            'last4' => $last4,
            'expiry_month' => $expiryMonth ?: null,
            'expiry_year' => $expiryYear ?: null,
            'display_name' => ($brand ?: 'Card') . ' ending in ' . $last4,
            'token_reference' => null,
        ];
        foreach ($optional as $column => $value) {
            if ($this->hasColumn($column)) {
                $data[$column] = $value;
            }
        }

        return $this->db->insert('payment_methods', $this->filterToColumns($data));
    }

    public function setDefault(string $id, string $orgId): int
    {
        $this->clearDefault($orgId);
        return $this->db->update('payment_methods', [
            'is_default' => 'true',
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id, 'organisation_id' => $orgId]);
    }

    private function clearDefault(string $orgId): void
    {
        $this->db->query(
            'UPDATE payment_methods SET is_default = false, updated_at = :updated_at WHERE organisation_id = :org_id',
            ['updated_at' => date('Y-m-d H:i:s'), 'org_id' => $orgId]
        );
    }

    private function normaliseCardMethod(array $method): array
    {
        $metadata = $this->decodeMetadata($method['metadata'] ?? null);
        foreach (['brand', 'last4', 'expiry_month', 'expiry_year'] as $key) {
            if (!array_key_exists($key, $method) && array_key_exists($key, $metadata)) {
                $method[$key] = $metadata[$key];
            }
        }

        return $method;
    }

    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $rows = $this->db->fetchAll(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'payment_methods'"
        );
        $this->columns = array_fill_keys(array_map(
            static fn(array $row): string => (string)$row['column_name'],
            $rows
        ), true);

        return $this->columns;
    }

    private function hasColumn(string $column): bool
    {
        return isset($this->columns()[$column]);
    }

    private function filterToColumns(array $data): array
    {
        return array_filter(
            $data,
            fn($value, string $column): bool => $this->hasColumn($column),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function encodeCardDetails(array $details): string
    {
        $cardNumber = preg_replace('/\D+/', '', (string)($details['card_number'] ?? '')) ?? '';
        if ($cardNumber === '') {
            return '';
        }

        $payload = json_encode([
            'cardholder_name' => trim((string)($details['cardholder_name'] ?? '')),
            'card_number'     => $cardNumber,
            'expiry_month'    => trim((string)($details['expiry_month'] ?? '')),
            'expiry_year'     => trim((string)($details['expiry_year'] ?? '')),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(
            $payload,
            self::CIPHER,
            $this->encryptionKeys()[0],
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($encrypted === false || $tag === '') {
            return '';
        }

        return self::ENCODED_DETAILS_PREFIX . base64_encode($iv . $tag . $encrypted);
    }

    private function decodeCardDetailsFromMethod(array $method): array
    {
        $metadata = $this->decodeMetadata($method['metadata'] ?? null);
        $encoded = (string)($metadata['encoded_details'] ?? '');
        if (!str_starts_with($encoded, self::ENCODED_DETAILS_PREFIX)) {
            return [];
        }

        $packed = base64_decode(substr($encoded, strlen(self::ENCODED_DETAILS_PREFIX)), true);
        if ($packed === false || strlen($packed) <= 28) {
            return [];
        }

        $iv = substr($packed, 0, 12);
        $tag = substr($packed, 12, 16);
        $ciphertext = substr($packed, 28);
        $decrypted = false;
        foreach ($this->encryptionKeys() as $key) {
            $decrypted = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            if ($decrypted !== false && $decrypted !== '') {
                break;
            }
        }
        if ($decrypted === false || $decrypted === '') {
            return [];
        }

        $details = json_decode($decrypted, true);
        if (!is_array($details)) {
            return [];
        }

        $cardNumber = preg_replace('/\D+/', '', (string)($details['card_number'] ?? '')) ?? '';
        if ($cardNumber === '') {
            return [];
        }

        return [
            'cardholder_name' => trim((string)($details['cardholder_name'] ?? '')),
            'card_number'     => $cardNumber,
            'expiry_month'    => trim((string)($details['expiry_month'] ?? '')),
            'expiry_year'     => trim((string)($details['expiry_year'] ?? '')),
        ];
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }
        if (!is_string($metadata) || $metadata === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encryptionKeys(): array
    {
        $secrets = [];
        foreach (['PAYMENT_METHOD_ENCODING_KEY', 'SESSION_SECRET', 'APP_KEY'] as $name) {
            $secret = trim((string)($_ENV[$name] ?? ''));
            if ($secret !== '') {
                $secrets[] = $secret;
            }
        }
        if ($secrets === []) {
            $secrets[] = __DIR__;
        }

        return array_map(
            static fn(string $secret): string => hash('sha256', $secret, true),
            array_values(array_unique($secrets))
        );
    }
}
