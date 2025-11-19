<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Banking\Bradesco;

use App\Models\BankApiConfig;
use App\Models\Fatura;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Banking\Bradesco\GenerateBradescoBoletoService;
use App\Services\Boleto\BoletoPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BradescoPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testDownloadsPdfWhenGatewayDoesNotReturnUrl(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.bradesco_boleto', array_merge(
            config('services.bradesco_boleto', []),
            [
                'base_url' => 'https://bradesco.test',
                'environment' => 'production',
                'id_produto' => '09',
                'negociacao' => '268100000000002863',
                'convenio' => '5992777',
                'cod_especie' => '02',
                'cnpj_raiz' => '51543631',
                'cnpj_filial' => '0001',
                'cnpj_controle' => '98',
                'pdf_disk' => 'public',
                'pdf_path' => 'boletos/bradesco',
            ]
        ));

        Storage::fake('public');

        Http::fake([
            'https://bradesco.test/boleto/cobranca-registro/v1/cobranca' => Http::response([
                'nossoNumero' => '79053',
                'nuTitulo' => '00000079053',
                'status' => 'registered',
                'linhaDigitavel' => '23790000000000000000000000000000000000000000',
                'urlPdf' => null,
            ], 200),
            'https://bradesco.test/boleto/cobranca-pdf/v1' => Http::response('%PDF-1.4 dummy', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $bankConfig = BankApiConfig::create([
            'bank_code' => BradescoApiClient::BANK_CODE,
            'environment' => 'production',
            'client_id' => 'client-id',
            'client_secret' => 'secret',
            'access_token' => 'token',
            'token_expires_at' => now()->addHour(),
            'settings' => [
                'base_url' => 'https://bradesco.test',
            ],
            'active' => true,
        ]);

        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
            'valor_total' => 10.00,
            'vencimento' => now()->addDays(4),
        ]);

        $fatura->contrato->locatario->update([
            'nome_razao_social' => 'Vinicius Dias Rios',
            'cpf_cnpj' => '39164523802',
            'email' => 'vinicius@example.org',
            'telefone' => '11959235036',
            'rua' => 'Rua Alice dos Santos Peixe',
            'numero' => '179',
            'bairro' => 'Jardim Selma',
            'cidade' => 'Sao Paulo',
            'estado' => 'SP',
            'cep' => '04443140',
        ]);

        $gateway = new BradescoBoletoGateway(new BradescoApiClient($bankConfig));
        $service = new GenerateBradescoBoletoService($gateway, $this->mockPdfService());

        $boleto = $service->handle($fatura)->fresh();

        $expectedPath = 'boletos/bradesco/79053.pdf';
        Storage::disk('public')->assertExists($expectedPath);

        $expectedUrl = Storage::disk('public')->url($expectedPath);
        $this->assertSame($expectedUrl, $boleto->pdf_url);

        $fatura->refresh();
        $this->assertSame($expectedUrl, $fatura->boleto_url);
    }

    private function mockPdfService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(BoletoPdfService::class);
        $mock->shouldReceive('storeAsAttachment')->andReturnNull();

        return $mock;
    }
}
