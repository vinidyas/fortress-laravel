<?php

namespace Tests\Feature\Api;

use App\Events\Boleto\BoletoRegistered;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Models\User;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Banking\Bradesco\GenerateBradescoBoletoService;
use Database\Seeders\PermissionsSeeder;
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
        $user = User::factory()->create();

        if (! empty($permissions)) {
            $user->forceFill(['permissoes' => $permissions])->save();
        }

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_store_requires_permission_to_generate_boleto(): void
    {
        $this->seed(PermissionsSeeder::class);
        $this->actingAsUser(['faturas.update', 'faturas.view']); // sem permissão de boleto

        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
        ]);

        $response = $this->postJson("/api/faturas/{$fatura->id}/boletos", []);
        $response->assertForbidden();
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

    public function test_sync_updates_bradesco_boletos(): void
    {
        $this->actingAsUser(['faturas.view']);

        $fatura = Fatura::factory()->create();

        $boleto = FaturaBoleto::create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => 'ext-1',
            'valor' => 100,
            'vencimento' => now()->addDays(3),
            'status' => FaturaBoleto::STATUS_REGISTERED,
        ]);

        $gateway = Mockery::mock(BradescoBoletoGateway::class);
        $gateway->shouldReceive('refreshStatus')
            ->once()
            ->with(Mockery::on(fn ($argument) => $argument->is($boleto)))
            ->andReturn($boleto);

        $this->app->instance(BradescoBoletoGateway::class, $gateway);

        $response = $this->postJson("/api/faturas/{$fatura->id}/boletos/sync");

        $response->assertOk()->assertJsonPath('meta.synced', 1);
    }
}
