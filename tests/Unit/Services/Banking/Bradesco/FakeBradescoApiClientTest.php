<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Banking\Bradesco;

use App\Models\BankApiConfig;
use App\Services\Banking\Bradesco\FakeBradescoApiClient;
use PHPUnit\Framework\TestCase;

class FakeBradescoApiClientTest extends TestCase
{
    public function testIssueBoletoMatchesExpectedSchema(): void
    {
        $config = new BankApiConfig([
            'bank_code' => 'bradesco',
            'environment' => 'sandbox',
            'certificate_path' => '/tmp/cert.pem',
            'certificate_password' => null,
            'settings' => [
                'key_path' => '/tmp/key.pem',
            ],
        ]);

        $client = new FakeBradescoApiClient($config);

        $payload = [
            'nuTitulo' => '12345678901',
            'nuCliente' => 'FAT-0001',
            'vlNominalTitulo' => '1500,75',
            'dtVencimentoTitulo' => '04.12.2026',
        ];

        $response = $client->issueBoleto($payload);

        $this->assertSame('12345678901', $response['nuTitulo']);
        $this->assertSame('12345678901', $response['nossoNumero']);
        $this->assertSame('FAT-0001', $response['numeroDocumento']);
        $this->assertEquals(1500.75, $response['valor']);
        $this->assertSame('04.12.2026', $response['dtVencimentoTitulo']);
        $this->assertSame('2026-12-04', $response['vencimento']);
        $this->assertSame('registered', $response['status']);
        $this->assertArrayHasKey('linhaDigitavel', $response);
        $this->assertArrayHasKey('codigoBarras', $response);
    }
}
