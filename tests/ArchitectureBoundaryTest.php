<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ArchitectureBoundaryTest extends TestCase
{
    /** @return list<string> */
    private function apiFrontendBoundaryFiles(): array
    {
        $root = dirname(__DIR__);

        return [
            $root . '/src/Controllers/CheckoutController.php',
            $root . '/src/Services/ApiKeyService.php',
            $root . '/src/Services/EntitlementService.php',
            $root . '/src/Services/PaymentIdempotencyService.php',
            $root . '/src/Services/PaymentMethodService.php',
        ];
    }

    public function testApiOrchestratedBoundariesDoNotUseLocalDb(): void
    {
        foreach ($this->apiFrontendBoundaryFiles() as $file) {
            $source = (string) file_get_contents($file);

            self::assertStringNotContainsString('use GI\\Core\\DB', $source, $file);
            self::assertStringNotContainsString('DB::', $source, $file);
            self::assertStringNotContainsString('getInstance()', $source, $file);
        }
    }

    public function testFrontendServiceUrlsAreDocumentedInEnvExample(): void
    {
        $envExample = (string) file_get_contents(dirname(__DIR__) . '/.env.example');

        self::assertStringContainsString('USER_API_URL=', $envExample);
        self::assertStringContainsString('CLIENT_API_URL=', $envExample);
        self::assertStringContainsString('OPERATIONS_API_URL=', $envExample);
    }
}
