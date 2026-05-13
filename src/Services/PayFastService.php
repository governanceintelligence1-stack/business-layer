<?php
declare(strict_types=1);

namespace GI\Services;

class PayFastService
{
    public function getProcessUrl(): string
    {
        $sandbox = filter_var($_ENV['PAYFAST_SANDBOX'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        return $sandbox
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process';
    }

    public function buildPaymentData(array $fields): array
    {
        // PayFast signature contract depends on field order.
        $ordered = [
            'merchant_id'   => $_ENV['PAYFAST_MERCHANT_ID'] ?? '',
            'merchant_key'  => $_ENV['PAYFAST_MERCHANT_KEY'] ?? '',
            'return_url'    => $_ENV['PAYFAST_RETURN_URL'] ?? '',
            'cancel_url'    => $_ENV['PAYFAST_CANCEL_URL'] ?? '',
            'notify_url'    => $_ENV['PAYFAST_NOTIFY_URL'] ?? '',
            'name_first'    => '',
            'name_last'     => '',
            'email_address' => '',
            'm_payment_id'  => '',
            'amount'        => '',
            'item_name'     => '',
            'item_description' => '',
            'custom_str1'   => '',
            'custom_str2'   => '',
            'custom_str3'   => '',
        ];

        foreach ($fields as $k => $v) {
            $ordered[$k] = (string) $v;
        }

        $ordered = array_filter($ordered, static fn($v) => $v !== '' && $v !== null);
        $ordered['signature'] = $this->generatePaymentFormSignature($ordered);
        return $ordered;
    }

    /**
     * Signature for redirect / hosted payment form (outbound to PayFast).
     */
    public function generatePaymentFormSignature(array $data): string
    {
        $passphrase = $_ENV['PAYFAST_PASSPHRASE'] ?? '';

        $filtered = array_filter(
            $data,
            static fn($v, $k) => $k !== 'signature' && $v !== '' && $v !== null,
            ARRAY_FILTER_USE_BOTH
        );

        $query = http_build_query($filtered);
        if ($passphrase !== '') {
            $query .= '&passphrase=' . urlencode($passphrase);
        }
        return md5($query);
    }

    /**
     * ITN (Instant Transaction Notification) security signature.
     * Mirrors PayFast’s PHP integrations: iterate POST fields in received order,
     * skip blank values (same rule as {@see https://github.com/woocommerce/woocommerce-gateway-payfast}),
     * {@see urlencode()} each value, append {@see PAYFAST_PASSPHRASE} when set, then MD5.
     */
    public function generateItnSignature(array $payload): string
    {
        $passphrase = (string) ($_ENV['PAYFAST_PASSPHRASE'] ?? '');
        $data       = $payload;
        unset($data['signature']);

        $parameter_string = '';
        foreach ($data as $key => $val) {
            if (empty($val)) {
                continue;
            }
            $parameter_string .= $key . '=' . urlencode((string) $val) . '&';
        }

        if ($passphrase !== '') {
            $parameter_string .= 'passphrase=' . urlencode($passphrase);
        } else {
            $parameter_string = rtrim($parameter_string, '&');
        }

        return md5($parameter_string);
    }

    public function isValidSignature(array $payload): bool
    {
        if (empty($payload['signature'])) {
            return false;
        }
        $received = strtolower((string) $payload['signature']);
        $expected = strtolower($this->generateItnSignature($payload));

        return hash_equals($expected, $received);
    }
}
