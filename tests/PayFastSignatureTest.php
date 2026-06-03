<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PayFastSignatureTest extends TestCase
{
    public function testRawItnSignaturePreservesReceivedFieldOrder(): void
    {
        $raw = 'm_payment_id=PF-123&payment_status=COMPLETE&amount_gross=10.00';
        $signature = payfast_build_itn_signature_from_raw($raw, 'secret');

        self::assertTrue(payfast_signatures_match($raw . '&signature=' . $signature, $signature, 'secret'));
    }
}
