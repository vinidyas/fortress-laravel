<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Models\User;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Boleto\BoletoPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BoletoPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_refreshes_boleto_via_consulta_before_generating_pdf(): void
    {
        $user = User::factory()->create([
            'permissoes' => ['faturas.view'],
        ]);

        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
        ]);

        $boleto = FaturaBoleto::create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => 'EXT123',
            'nosso_numero' => '79053',
            'valor' => 100,
            'vencimento' => now()->addDays(5),
            'status' => FaturaBoleto::STATUS_REGISTERED,
            'registrado_em' => now(),
        ]);

        $gatewayMock = Mockery::mock(BradescoBoletoGateway::class);
        $gatewayMock->shouldReceive('refreshStatus')
            ->once()
            ->andReturnUsing(function (FaturaBoleto $model) {
                $model->linha_digitavel = '23791.11111 11111.111118 11111.111118 1 11110000010000';
                $model->codigo_barras = '23791111111111111111111111111111111111111111';
                $model->response_payload = ['status' => 'registered'];
                $model->save();

                return $model;
            });
        $this->instance(BradescoBoletoGateway::class, $gatewayMock);

        $pdfDocument = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdfDocument->shouldReceive('output')->once()->andReturn('%PDF-FAKE%');

        $pdfServiceMock = Mockery::mock(BoletoPdfService::class);
        $pdfServiceMock->shouldReceive('generate')->once()->andReturn($pdfDocument);
        $pdfServiceMock->shouldReceive('storeAsAttachment')->once()->andReturnNull();
        $this->instance(BoletoPdfService::class, $pdfServiceMock);

        $response = $this->actingAs($user, 'sanctum')->get(route('api.boletos.pdf', [
            'boleto' => $boleto->id,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertSee('%PDF-FAKE%', false);

        $this->assertNotNull($boleto->fresh()->linha_digitavel);
    }
}
