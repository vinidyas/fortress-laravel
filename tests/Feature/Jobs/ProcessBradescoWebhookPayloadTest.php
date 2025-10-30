<?php

namespace Tests\Feature\Jobs;

use App\Events\Boleto\BoletoCanceled;
use App\Events\Boleto\BoletoPaid;
use App\Jobs\Bradesco\ProcessBradescoWebhookPayload;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Models\FinancialTransaction;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class ProcessBradescoWebhookPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testItMarksInvoiceAsPaidAndConciliatesTransactions(): void
    {
        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
            'metodo_pagamento' => null,
            'valor_total' => 1250.50,
            'valor_pago' => null,
            'pago_em' => null,
        ]);

        $boleto = FaturaBoleto::query()->create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => 'ext-123',
            'nosso_numero' => '000123456789',
            'document_number' => 'DOC-001',
            'linha_digitavel' => null,
            'codigo_barras' => null,
            'valor' => 1250.50,
            'vencimento' => now()->addDays(5)->toDateString(),
            'status' => FaturaBoleto::STATUS_REGISTERED,
        ]);

        $transaction = FinancialTransaction::factory()
            ->for($fatura)
            ->create([
                'tipo' => 'credito',
                'valor' => 1250.50,
                'status' => 'pendente',
                'data_ocorrencia' => now()->toDateString(),
            ]);

        $payload = [
            'externalId' => 'ext-123',
            'nossoNumero' => '000123456789',
        ];

        $liquidadoEm = Carbon::now();

        $gateway = Mockery::mock(BradescoBoletoGateway::class);
        $gateway->expects('refreshStatus')->once()->andReturnUsing(function (FaturaBoleto $argument) use ($liquidadoEm) {
            $argument->status = FaturaBoleto::STATUS_PAID;
            $argument->valor_pago = 1250.50;
            $argument->liquidado_em = $liquidadoEm;
            $argument->pdf_url = 'https://example.test/boletos/123.pdf';
            $argument->last_synced_at = $liquidadoEm;
            $argument->save();

            return $argument->refresh();
        });

        Event::fake([BoletoPaid::class, BoletoCanceled::class]);

        $job = new ProcessBradescoWebhookPayload($payload);
        $job->handle($gateway);

        $fatura->refresh();
        $transaction->refresh();
        $boleto->refresh();

        $this->assertSame('Paga', $fatura->status);
        $this->assertSame('Boleto', $fatura->metodo_pagamento);
        $this->assertEquals(1250.50, (float) $fatura->valor_pago);
        $this->assertSame($liquidadoEm->toDateString(), $fatura->pago_em?->toDateString());
        $this->assertSame(FaturaBoleto::STATUS_PAID, $boleto->status);

        $this->assertSame('conciliado', $transaction->status);
        $this->assertIsArray($transaction->meta);
        $this->assertArrayHasKey('boleto_sync', $transaction->meta);
        $this->assertSame($boleto->id, $transaction->meta['boleto_sync']['fatura_boleto_id']);

        Event::assertDispatchedTimes(BoletoPaid::class, 1);
        Event::assertNotDispatched(BoletoCanceled::class);
    }

    public function testItCancelsInvoiceWhenBoletoIsCanceled(): void
    {
        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
            'metodo_pagamento' => null,
        ]);

        $boleto = FaturaBoleto::query()->create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => 'ext-999',
            'nosso_numero' => '987654321000',
            'document_number' => 'DOC-999',
            'valor' => 890.00,
            'vencimento' => now()->addDays(10)->toDateString(),
            'status' => FaturaBoleto::STATUS_REGISTERED,
        ]);

        $transaction = FinancialTransaction::factory()
            ->for($fatura)
            ->create([
                'tipo' => 'credito',
                'valor' => 890.00,
                'status' => 'pendente',
                'data_ocorrencia' => now()->toDateString(),
            ]);

        $payload = [
            'externalId' => 'ext-999',
            'nossoNumero' => '987654321000',
        ];

        $syncedAt = Carbon::now();

        $gateway = Mockery::mock(BradescoBoletoGateway::class);
        $gateway->expects('refreshStatus')->once()->andReturnUsing(function (FaturaBoleto $argument) use ($syncedAt) {
            $argument->status = FaturaBoleto::STATUS_CANCELED;
            $argument->last_synced_at = $syncedAt;
            $argument->save();

            return $argument->refresh();
        });

        Event::fake([BoletoPaid::class, BoletoCanceled::class]);

        $job = new ProcessBradescoWebhookPayload($payload);
        $job->handle($gateway);

        $fatura->refresh();
        $transaction->refresh();
        $boleto->refresh();

        $this->assertSame('Cancelada', $fatura->status);
        $this->assertSame('Boleto', $fatura->metodo_pagamento);
        $this->assertNull($fatura->valor_pago);
        $this->assertSame(FaturaBoleto::STATUS_CANCELED, $boleto->status);
        $this->assertSame('cancelado', $transaction->status);
        $this->assertArrayHasKey('boleto_sync', $transaction->meta);

        Event::assertDispatchedTimes(BoletoCanceled::class, 1);
        Event::assertNotDispatched(BoletoPaid::class);
    }
}
