<?php

namespace Tests\Feature\Api;

use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Models\User;
use App\Events\Boleto\BoletoRegistered;
use App\Services\Banking\Bradesco\GenerateBradescoBoletoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class FaturaBoletoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function actingAsUser(array $permissions = []): User
    {
        $user = User::factory()->create([
            'permissoes' => $permissions,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_store_creates_boleto_via_service(): void
    {
        $this->actingAsUser(['faturas.update', 'faturas.view']);

        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
        ]);

        $mockService = Mockery::mock(GenerateBradescoBoletoService::class);
        $this->app->instance(GenerateBradescoBoletoService::class, $mockService);

        $mockService->expects('handle')->once()->withArgs(function ($argumentFatura, $payload) use ($fatura) {
            return $argumentFatura instanceof Fatura && $argumentFatura->is($fatura) && is_array($payload);
        })
            ->andReturnUsing(function () use ($fatura) {
                return FaturaBoleto::query()->create([
                    'fatura_id' => $fatura->id,
                    'bank_code' => 'bradesco',
                    'external_id' => 'service-ext-1',
                    'nosso_numero' => '99887766',
                    'document_number' => 'DOC-123',
                    'valor' => 500.00,
                    'vencimento' => now()->addDays(7)->toDateString(),
                    'status' => FaturaBoleto::STATUS_REGISTERED,
                ]);
            });

        $response = $this->postJson("/api/faturas/{$fatura->id}/boletos", []);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', FaturaBoleto::STATUS_REGISTERED)
            ->assertJsonPath('data.nosso_numero', '99887766')
            ->assertJsonPath('meta.message', 'Boleto gerado com sucesso.');

        $this->assertDatabaseHas('fatura_boletos', [
            'fatura_id' => $fatura->id,
            'external_id' => 'service-ext-1',
        ]);

    }

    public function test_index_returns_boletos(): void
    {
        $this->actingAsUser(['faturas.view']);

        $fatura = Fatura::factory()->create();

        FaturaBoleto::query()->create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => 'ext-1',
            'valor' => 400,
            'vencimento' => now()->addDays(5)->toDateString(),
            'status' => FaturaBoleto::STATUS_REGISTERED,
        ]);

        FaturaBoleto::query()->create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => 'ext-2',
            'valor' => 450,
            'vencimento' => now()->addDays(10)->toDateString(),
            'status' => FaturaBoleto::STATUS_PENDING,
        ]);

        $response = $this->getJson("/api/faturas/{$fatura->id}/boletos");

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_show_returns_not_found_when_boleto_does_not_belong_to_fatura(): void
    {
        $this->actingAsUser(['faturas.view']);

        $faturaA = Fatura::factory()->create();
        $faturaB = Fatura::factory()->create();

        $boleto = FaturaBoleto::query()->create([
            'fatura_id' => $faturaB->id,
            'bank_code' => 'bradesco',
            'external_id' => 'other-ext',
            'valor' => 300,
            'vencimento' => now()->addDays(4)->toDateString(),
            'status' => FaturaBoleto::STATUS_REGISTERED,
        ]);

        $response = $this->getJson("/api/faturas/{$faturaA->id}/boletos/{$boleto->id}");

        $response->assertNotFound();
    }
}
