<?php

declare(strict_types=1);

/**
 * PayFast ITN helpers.
 *
 * Important for inbound ITN signature validation:
 * - Prefer the raw POST body (php://input) before PHP populates $_POST.
 * - Exclude only the signature field from the digest input.
 * - Preserve the received field order and encoded pairs (including empty values).
 */

if (!function_exists('gi_env_value')) {
    /**
     * Read env after application bootstrap (Dotenv / server env). Does not parse .env on its own.
     */
    function gi_env_value(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            $v = (string) $_ENV[$key];

            return $v === '' ? $default : $v;
        }
        $g = getenv($key);

        if ($g === false || $g === '') {
            return $default;
        }

        return (string) $g;
    }
}

if (!function_exists('payfast_parse_raw_post')) {
    /**
     * @return array<string, string>
     */
    function payfast_parse_raw_post(string $rawBody): array
    {
        $data = [];
        if ($rawBody === '') {
            return $data;
        }

        $pairs = explode('&', $rawBody);
        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $data[urldecode($key)] = urldecode($value);
        }

        return $data;
    }
}

if (!function_exists('payfast_build_itn_signature_from_raw')) {
    function payfast_build_itn_signature_from_raw(string $rawBody, string $passphrase = ''): string
    {
        $signatureParts = [];
        $pairs = explode('&', $rawBody);

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }
            [$rawKey] = array_pad(explode('=', $pair, 2), 2, '');
            $decodedKey = urldecode($rawKey);
            if ($decodedKey === 'signature') {
                continue;
            }
            $signatureParts[] = $pair;
        }

        $payload = implode('&', $signatureParts);
        if ($passphrase !== '') {
            $payload .= '&passphrase=' . urlencode($passphrase);
        }

        return md5($payload);
    }
}

if (!function_exists('payfast_signatures_match')) {
    function payfast_signatures_match(string $rawBody, string $receivedSignature, string $passphrase = ''): bool
    {
        $receivedSignature = strtolower(trim($receivedSignature));
        if ($receivedSignature === '') {
            return false;
        }

        $calculatedSignature = strtolower(payfast_build_itn_signature_from_raw($rawBody, $passphrase));

        return hash_equals($calculatedSignature, $receivedSignature);
    }
}
