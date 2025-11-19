<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Banking\Bradesco;

use App\Events\Boleto\BoletoRegistered;
use App\Models\BankApiConfig;
use App\Models\Fatura;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Banking\Bradesco\FakeBradescoApiClient;
use App\Services\Banking\Bradesco\GenerateBradescoBoletoService;
use App\Services\Boleto\BoletoPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class GenerateBradescoBoletoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.bradesco_boleto', array_merge(
            config('services.bradesco_boleto', []),
            [
                'environment' => 'sandbox',
                'id_produto' => '09',
                'negociacao' => '000000000000000000',
                'convenio' => '1234567',
                'cod_especie' => '02',
                'cnpj_raiz' => '12345678',
                'cnpj_filial' => '0001',
                'cnpj_controle' => '23',
                'codigo_usuario' => 'USR00001',
                'registra_titulo' => 'S',
                'tipo_vencimento' => '0',
                'indicador_moeda' => '1',
                'quantidade_moeda' => '00000000000000000',
                'tp_protesto' => '0',
                'prazo_protesto' => '00',
                'tipo_decurso' => '0',
                'tipo_dias_decurso' => '0',
                'tipo_prazo_tres' => '000',
                'indicador_aceite_sacado' => '2',
                'sandbox_pdf_url' => 'https://example.test/boleto.pdf',
                'sandbox_use_fixtures' => false,
            ]
        ));
    }

    public function testHandleIssuesAndPersistsBoletoWithMaskedPayload(): void
    {
        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
            'valor_total' => 985.50,
            'vencimento' => now()->addDays(10),
        ]);

        $fatura->contrato->locatario->update([
            'nome_razao_social' => 'Maria Teste',
            'cpf_cnpj' => '98765432100',
            'email' => 'maria@example.com',
            'telefone' => '11987654321',
        ]);

        $clientConfig = new BankApiConfig([
            'bank_code' => BradescoApiClient::BANK_CODE,
            'environment' => 'sandbox',
        ]);

        $service = new GenerateBradescoBoletoService(
            new BradescoBoletoGateway(new FakeBradescoApiClient($clientConfig)),
            $this->mockPdfService()
        );

        Event::fake([BoletoRegistered::class]);

        $boleto = $service->handle($fatura);

        $this->assertTrue($boleto->exists);
        $this->assertSame(BradescoApiClient::BANK_CODE, $boleto->bank_code);
        $this->assertSame('registered', $boleto->status);
        $this->assertNotEmpty($boleto->nosso_numero);
        $this->assertNotNull($boleto->pdf_url);

        $payload = $boleto->payload;
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('nomePagador', $payload);
        $this->assertStringContainsString('*', $payload['nomePagador']);
        $this->assertStringContainsString('*', $payload['nuCpfcnpjPagador']);

        $response = $boleto->response_payload;
        $this->assertIsArray($response);
        $this->assertStringContainsString('*', $response['linhaDigitavel']);

        $fatura->refresh();
        $this->assertSame($boleto->nosso_numero, $fatura->nosso_numero);
        $this->assertSame($boleto->pdf_url, $fatura->boleto_url);

        Event::assertDispatched(BoletoRegistered::class, function (BoletoRegistered $event) use ($boleto) {
            return $event->boleto->is($boleto);
        });
    }

    public function testHandleReusesExistingRegisteredBoleto(): void
    {
        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
            'valor_total' => 1200.00,
            'vencimento' => now()->addDays(5),
        ]);

        $clientConfig = new BankApiConfig([
            'bank_code' => BradescoApiClient::BANK_CODE,
            'environment' => 'sandbox',
        ]);

        $service = new GenerateBradescoBoletoService(
            new BradescoBoletoGateway(new FakeBradescoApiClient($clientConfig)),
            $this->mockPdfService()
        );

        Event::fake([BoletoRegistered::class]);

        $first = $service->handle($fatura);
        $this->assertTrue($first->wasRecentlyCreated);
        Event::assertDispatchedTimes(BoletoRegistered::class, 1);

        Event::fake([BoletoRegistered::class]);
        $second = $service->handle($fatura->fresh());

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $fatura->boletos()->count());
        Event::assertNothingDispatched();
    }

    private function mockPdfService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(BoletoPdfService::class);
        $mock->shouldReceive('storeAsAttachment')->andReturnNull();

        return $mock;
    }
}
