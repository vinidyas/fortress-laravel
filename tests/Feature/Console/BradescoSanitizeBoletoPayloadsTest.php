<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\Bradesco\SanitizeBoletoPayloads;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BradescoSanitizeBoletoPayloadsTest extends TestCase
{
    use RefreshDatabase;

    public function testCommandSanitizesStoredPayloads(): void
    {
        /** @var Fatura $fatura */
        $fatura = Fatura::factory()->create();

        $boleto = FaturaBoleto::query()->create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => '1234567890',
            'valor' => 1000,
            'vencimento' => now()->addDay()->toDateString(),
            'status' => FaturaBoleto::STATUS_REGISTERED,
            'payload' => [
                'nuCpfcnpjPagador' => '12345678901',
                'nomePagador' => 'Joao da Silva',
            ],
            'response_payload' => [
                'linhaDigitavel' => '23790123456789012345678901234567890123456789',
                'nuCliente' => 'CLIENTE123',
            ],
            'webhook_payload' => [
                'event' => 'boleto_liquidado',
                'nossoNumero' => '12345678901234567890',
            ],
        ]);

        $this->artisan('bradesco:sanitize-boleto-payloads')
            ->assertExitCode(SanitizeBoletoPayloads::SUCCESS)
            ->expectsOutputToContain('boletos sanitizados');

        $boleto->refresh();

        $this->assertSame('J***********a', $boleto->payload['nomePagador']);
        $this->assertMatchesRegularExpression('/^123\*+\d{3}$/', $boleto->payload['nuCpfcnpjPagador']);
        $this->assertMatchesRegularExpression('/^2379\*+\d{4}$/', $boleto->response_payload['linhaDigitavel']);
        $this->assertMatchesRegularExpression('/^123\*+\d{3}$/', $boleto->webhook_payload['nossoNumero']);
    }

    public function testDryRunDoesNotPersistChanges(): void
    {
        /** @var Fatura $fatura */
        $fatura = Fatura::factory()->create();

        $boleto = FaturaBoleto::query()->create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => '777',
            'valor' => 500,
            'vencimento' => now()->addDay()->toDateString(),
            'status' => FaturaBoleto::STATUS_REGISTERED,
            'payload' => [
                'nomePagador' => 'Maria Teste',
            ],
        ]);

        $this->artisan('bradesco:sanitize-boleto-payloads --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('seriam atualizados');

        $this->assertSame('Maria Teste', $boleto->fresh()->payload['nomePagador']);
    }
}
