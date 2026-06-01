<?php

declare(strict_types=1);

namespace GI\Services;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PayFast integration: outbound hosted-payment form and inbound ITN validation.
 *
 * ITN checks (align with PayFast guidance): signature, source IP, amount (optional),
 * merchant id, server-side validate ping. Use {@see PAYFAST_ITN_SKIP_IP_VALIDATION}
 * and {@see PAYFAST_ITN_SKIP_SERVER_VALIDATION} behind reverse proxies (e.g. ngrok)
 * where {@see REMOTE_ADDR} is not PayFast.
 */
final class PayFastService
{
    /** @var list<string> */
    private const PAYFAST_HOSTS = [
        'www.payfast.co.za',
        'sandbox.payfast.co.za',
        'w1w.payfast.co.za',
        'w2w.payfast.co.za',
    ];

    /** @var list<string> */
    private const PAYMENT_FIELD_ORDER = [
        'merchant_id',
        'merchant_key',
        'return_url',
        'cancel_url',
        'notify_url',
        'name_first',
        'name_last',
        'email_address',
        'm_payment_id',
        'amount',
        'item_name',
        'item_description',
        'custom_str1',
        'custom_str2',
        'custom_str3',
        'custom_str4',
        'custom_str5',
        'custom_int1',
        'custom_int2',
        'custom_int3',
        'custom_int4',
        'custom_int5',
    ];

    private string $merchantId;
    private string $merchantKey;
    private string $passphrase;
    private bool $sandbox;
    private LoggerInterface $logger;

    public function __construct(
        string $merchantId,
        string $merchantKey,
        string $passphrase = '',
        bool $sandbox = false,
        ?LoggerInterface $logger = null
    ) {
        if (trim($merchantId) === '') {
            throw new InvalidArgumentException('PAYFAST_MERCHANT_ID must not be empty.');
        }
        if (trim($merchantKey) === '') {
            throw new InvalidArgumentException('PAYFAST_MERCHANT_KEY must not be empty.');
        }

        $this->merchantId  = trim($merchantId);
        $this->merchantKey = trim($merchantKey);
        $this->passphrase  = trim($passphrase);
        $this->sandbox     = $sandbox;
        $this->logger      = $logger ?? new NullLogger();
    }

    public static function fromEnv(?LoggerInterface $logger = null): self
    {
        $s = self::tryFromEnv($logger);
        if ($s === null) {
            throw new InvalidArgumentException(
                'PAYFAST_MERCHANT_ID and PAYFAST_MERCHANT_KEY must be set in the environment.'
            );
        }

        return $s;
    }

    public static function tryFromEnv(?LoggerInterface $logger = null): ?self
    {
        $mid  = self::envString('PAYFAST_MERCHANT_ID');
        $key  = self::envString('PAYFAST_MERCHANT_KEY');
        $pass = self::envString('PAYFAST_PASSPHRASE');
        if ($mid === '' || $key === '') {
            return null;
        }
        $envName = strtolower(self::envString('PAYFAST_ENV'));
        if ($envName !== '') {
            $sandbox = $envName === 'sandbox';
        } else {
            $sandbox = filter_var(self::envRaw('PAYFAST_SANDBOX') ?? 'true', FILTER_VALIDATE_BOOLEAN);
        }

        return new self($mid, $key, $pass, $sandbox, $logger);
    }

    private static function envRaw(string $name): ?string
    {
        if (array_key_exists($name, $_ENV)) {
            return (string) $_ENV[$name];
        }
        $g = getenv($name);

        return $g === false ? null : (string) $g;
    }

    private static function envString(string $name): string
    {
        $v = self::envRaw($name);

        return $v === null ? '' : trim($v);
    }

    /**
     * PayFast requires return_url, cancel_url, and notify_url on the payment form.
     * Uses env when set; otherwise builds from APP_URL so redirects work when only APP_URL is configured.
     *
     * @return array{return_url: string, cancel_url: string, notify_url: string}
     */
    private static function defaultGatewayUrls(): array
    {
        $app = rtrim(self::envString('APP_URL'), '/');
        $return = self::envString('PAYFAST_RETURN_URL');
        $cancel  = self::envString('PAYFAST_CANCEL_URL');
        $notify  = self::envString('PAYFAST_NOTIFY_URL');
        if ($return === '' && $app !== '') {
            $return = $app . '/checkout/return';
        }
        if ($cancel === '' && $app !== '') {
            $cancel = $app . '/checkout/cancel';
        }
        if ($notify === '' && $app !== '') {
            $notify = $app . '/checkout/notify';
        }

        return [
            'return_url' => $return,
            'cancel_url' => $cancel,
            'notify_url' => $notify,
        ];
    }

    public function getProcessUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process';
    }

    /**
     * @param array<string, string|int|float> $fields
     *
     * @return array<string, string>
     */
    public function buildPaymentData(array $fields): array
    {
        $ordered = array_fill_keys(self::PAYMENT_FIELD_ORDER, '');
        $ordered['merchant_id']  = $this->merchantId;
        $ordered['merchant_key'] = $this->merchantKey;

        foreach ($fields as $k => $v) {
            $ordered[(string) $k] = (string) $v;
        }

        $defaults = self::defaultGatewayUrls();
        foreach ($defaults as $key => $val) {
            if ($val !== '' && (($ordered[$key] ?? '') === '')) {
                $ordered[$key] = $val;
            }
        }

        $ordered = array_filter(
            $ordered,
            static fn(string $v): bool => $v !== '',
        );

        $ordered['signature'] = $this->generatePaymentFormSignature($ordered);

        return $ordered;
    }

    /**
     * @param array<string, string> $data
     */
    public function generatePaymentFormSignature(array $data): string
    {
        $filtered = array_filter(
            $data,
            static fn($v, string $k): bool => $k !== 'signature' && $v !== '',
            ARRAY_FILTER_USE_BOTH
        );

        $query = http_build_query($filtered);
        if ($this->passphrase !== '') {
            $query .= '&passphrase=' . urlencode($this->passphrase);
        }

        return md5($query);
    }

    /**
     * @param array<string, string> $payload
     */
    public function validateItn(
        array $payload,
        string $remoteAddr,
        ?string $expectedAmount = null,
        ?string $rawPostBody = null
    ): bool {
        $pfPaymentId = $payload['pf_payment_id'] ?? 'unknown';
        $context     = ['pf_payment_id' => $pfPaymentId];

        if (!$this->isValidItnSignature($payload, $rawPostBody)) {
            $this->logger->warning('PayFast ITN: signature mismatch.', $context);

            return false;
        }

        if (!$this->isValidSourceIp($remoteAddr)) {
            $this->logger->warning('PayFast ITN: invalid source IP.', $context + ['remote_addr' => $remoteAddr]);

            return false;
        }

        if ($expectedAmount !== null) {
            $received = $payload['amount_gross'] ?? '';
            if (!$this->amountsMatch((string) $received, $expectedAmount)) {
                $this->logger->warning('PayFast ITN: amount mismatch.', $context + [
                    'received' => $received,
                    'expected' => $expectedAmount,
                ]);

                return false;
            }
        }

        $itnMerchantId = trim((string) ($payload['merchant_id'] ?? ''));
        if ($itnMerchantId !== $this->merchantId) {
            $this->logger->warning('PayFast ITN: merchant_id mismatch.', $context);

            return false;
        }

        if (!$this->isValidData($payload)) {
            $this->logger->warning('PayFast ITN: server-side data validation failed.', $context);

            return false;
        }

        $this->logger->info('PayFast ITN: all checks passed.', $context);

        return true;
    }

    /**
     * Prefer signature validation from the raw POST body (field order + empty values preserved).
     * Falls back to legacy array-based candidates when raw body is empty (e.g. local simulations).
     *
     * @param array<string, string> $payload
     */
    public function isValidItnSignature(array $payload, ?string $rawPostBody): bool
    {
        if ($rawPostBody !== null && $rawPostBody !== '') {
            $received = strtolower(trim((string) ($payload['signature'] ?? '')));

            return $received !== ''
                && payfast_signatures_match($rawPostBody, $received, $this->passphrase);
        }

        return $this->isValidSignature($payload);
    }

    /**
     * @param array<string, string> $payload
     */
    public function isValidSignature(array $payload): bool
    {
        if (empty($payload['signature'])) {
            return false;
        }

        $received = strtolower(trim((string) $payload['signature']));

        $candidates = [
            strtolower($this->generateItnSignaturePostOrder($payload)),
            strtolower($this->generateItnSignatureSorted($payload)),
        ];

        foreach ($candidates as $expected) {
            if (hash_equals($expected, $received)) {
                return true;
            }
        }

        return false;
    }

    public function isValidSourceIp(string $remoteAddr): bool
    {
        if ($this->isIpValidationSkippedByEnv()) {
            $this->logger->notice('PayFast ITN: IP validation skipped (PAYFAST_ITN_SKIP_IP_VALIDATION).');

            return true;
        }

        if ($this->sandbox && in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $validIps = $this->resolvePayFastIps();
        if ($validIps === []) {
            $this->logger->error('PayFast ITN: could not resolve PayFast hostnames for IP validation.');

            return false;
        }

        return in_array($remoteAddr, $validIps, true);
    }

    /**
     * @param array<string, string> $payload
     */
    public function isValidData(array $payload): bool
    {
        if ($this->isServerValidationSkippedByEnv()) {
            $this->logger->notice('PayFast ITN: server-side validation skipped (PAYFAST_ITN_SKIP_SERVER_VALIDATION).');

            return true;
        }

        $paramString = $this->buildValidateReplayString($payload);
        $host        = $this->sandbox
            ? 'https://sandbox.payfast.co.za'
            : 'https://www.payfast.co.za';
        $url         = $host . '/eng/query/validate';

        if (extension_loaded('curl')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $this->logger->error('PayFast ITN: curl_init failed for data validation.');

                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $paramString,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Content-Length: ' . (string) strlen($paramString),
                ],
            ]);
            $response  = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                $this->logger->error('PayFast ITN: cURL error during data validation.', ['curl_error' => $curlError]);

                return false;
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                        . 'Content-Length: ' . strlen($paramString) . "\r\n",
                    'content' => $paramString,
                    'timeout' => 30.0,
                ],
            ]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                $this->logger->error('PayFast ITN: HTTP fallback failed for data validation.');

                return false;
            }
        }

        return strtoupper(trim((string) $response)) === 'VALID';
    }

    /**
     * @param array<string, string> $payload
     */
    public function isComplete(array $payload): bool
    {
        return strtoupper(trim($payload['payment_status'] ?? '')) === 'COMPLETE';
    }

    /**
     * @param array<string, string> $payload
     */
    private function generateItnSignaturePostOrder(array $payload): string
    {
        $data = $payload;
        unset($data['signature']);

        $paramString = '';
        foreach ($data as $key => $val) {
            if ($val === null) {
                continue;
            }
            $paramString .= $key . '=' . urlencode((string) $val) . '&';
        }

        if ($this->passphrase !== '') {
            $paramString .= 'passphrase=' . urlencode($this->passphrase);
        } else {
            $paramString = rtrim($paramString, '&');
        }

        return md5($paramString);
    }

    /**
     * @param array<string, string> $payload
     */
    private function generateItnSignatureSorted(array $payload): string
    {
        $data = $payload;
        unset($data['signature']);

        if ($this->passphrase !== '') {
            $data['passphrase'] = $this->passphrase;
        }

        ksort($data, SORT_STRING);

        $paramString = '';
        foreach ($data as $key => $val) {
            if ($val === null) {
                continue;
            }
            $paramString .= $key . '=' . urlencode((string) $val) . '&';
        }

        return md5(rtrim($paramString, '&'));
    }

    /**
     * ITN replay for PayFast /eng/query/validate.
     *
     * PayFast expects the received fields to be posted back without the
     * signature field; signature validation is performed separately.
     *
     * @param array<string, string> $payload
     */
    private function buildValidateReplayString(array $payload): string
    {
        $paramString = '';
        foreach ($payload as $key => $val) {
            if ($key === 'signature') {
                continue;
            }
            if ($val === null) {
                continue;
            }
            $paramString .= $key . '=' . urlencode((string) $val) . '&';
        }

        return rtrim($paramString, '&');
    }

    /**
     * @return list<string>
     */
    private function resolvePayFastIps(): array
    {
        $validIps = [];
        foreach (self::PAYFAST_HOSTS as $hostname) {
            $ips = gethostbynamel($hostname);
            if ($ips !== false) {
                $validIps = array_merge($validIps, $ips);
            }
            $records = @dns_get_record($hostname, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $rec) {
                    if (!empty($rec['ipv6'])) {
                        $validIps[] = $rec['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($validIps));
    }

    private function amountsMatch(string $received, string $expected): bool
    {
        $r = number_format((float) $received, 2, '.', '');
        $e = number_format((float) $expected, 2, '.', '');

        return $r === $e;
    }

    private function isIpValidationSkippedByEnv(): bool
    {
        return filter_var(
            self::envRaw('PAYFAST_ITN_SKIP_IP_VALIDATION') ?? 'false',
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function isServerValidationSkippedByEnv(): bool
    {
        $configured = self::envRaw('PAYFAST_ITN_SKIP_SERVER_VALIDATION');
        if ($configured !== null && trim($configured) !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        // Local sandbox testing through ngrok often cannot satisfy PayFast's
        // server replay check reliably. Keep live payments strict by default.
        return $this->sandbox && $this->isIpValidationSkippedByEnv();
    }
}
