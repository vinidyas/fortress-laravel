<?php

namespace Tests\Feature\Api;

use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContratosTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsContratoApiUser(array $permissions = []): User
    {
        $user = User::factory()->create([
            'permissoes' => $permissions,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_lists_contratos_with_filters(): void
    {
        $this->actingAsContratoApiUser(['contratos.view']);

        Contrato::factory()->count(3)->create();

        $response = $this->getJson('/api/contratos');

        $response->assertOk()->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }

    public function test_creates_contrato_successfully(): void
    {
        $this->actingAsContratoApiUser(['contratos.create', 'contratos.view']);

        $imovel = Imovel::factory()->create();
        $locador = Pessoa::factory()->create();
        $locatario = Pessoa::factory()->create();

        $payload = [
            'codigo_contrato' => 'CTR-001',
            'imovel_id' => $imovel->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'data_inicio' => now()->toDateString(),
            'data_fim' => now()->addMonths(12)->toDateString(),
            'dia_vencimento' => 10,
            'valor_aluguel' => '2500.00',
            'reajuste_indice' => 'IGPM',
            'garantia_tipo' => 'SemGarantia',
            'caucao_valor' => '0.00',
            'taxa_adm_percentual' => '0.00',
            'status' => 'Ativo',
        ];

        $response = $this->postJson('/api/contratos', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('contratos', ['codigo_contrato' => 'CTR-001']);
    }

    public function test_prevents_duplicate_codigo_contrato(): void
    {
        $this->actingAsContratoApiUser(['contratos.create']);

        $contrato = Contrato::factory()->create(['codigo_contrato' => 'CTR-001']);

        $payload = [
            'codigo_contrato' => 'CTR-001',
            'imovel_id' => $contrato->imovel_id,
            'locador_id' => $contrato->locador_id,
            'locatario_id' => $contrato->locatario_id,
            'data_inicio' => now()->toDateString(),
            'dia_vencimento' => 10,
            'valor_aluguel' => '1000.00',
            'status' => 'Ativo',
        ];

        $response = $this->postJson('/api/contratos', $payload);

        $response->assertStatus(422);
    }

    public function test_blocks_second_active_contrato_for_same_imovel(): void
    {
        $this->actingAsContratoApiUser(['contratos.create']);

        $imovel = Imovel::factory()->create();
        $locador = Pessoa::factory()->create();
        $locatario = Pessoa::factory()->create();

        Contrato::factory()->create([
            'imovel_id' => $imovel->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'status' => 'Ativo',
            'data_inicio' => Carbon::now()->subMonths(1)->toDateString(),
            'data_fim' => Carbon::now()->addMonths(11)->toDateString(),
        ]);

        $payload = [
            'codigo_contrato' => 'CTR-XYZ',
            'imovel_id' => $imovel->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'data_inicio' => Carbon::now()->toDateString(),
            'dia_vencimento' => 5,
            'valor_aluguel' => '1800.00',
            'status' => 'Ativo',
        ];

        $response = $this->postJson('/api/contratos', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['imovel_id']);
    }

    public function test_update_prevents_moving_active_contrato_without_status_field(): void
    {
        $this->actingAsContratoApiUser(['contratos.update']);

        $imovelA = Imovel::factory()->create();
        $imovelB = Imovel::factory()->create();
        $locador = Pessoa::factory()->create();
        $locatario = Pessoa::factory()->create();

        Contrato::factory()->create([
            'imovel_id' => $imovelA->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'status' => 'Ativo',
            'data_inicio' => Carbon::now()->subMonths(1)->toDateString(),
            'data_fim' => null,
        ]);

        $contratoParaAtualizar = Contrato::factory()->create([
            'imovel_id' => $imovelB->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'status' => 'Ativo',
            'data_inicio' => Carbon::now()->subMonths(2)->toDateString(),
            'data_fim' => null,
        ]);

        $payload = [
            'codigo_contrato' => $contratoParaAtualizar->codigo_contrato,
            'imovel_id' => $imovelA->id,
            'locador_id' => $contratoParaAtualizar->locador_id,
            'locatario_id' => $contratoParaAtualizar->locatario_id,
            'data_inicio' => $contratoParaAtualizar->data_inicio->toDateString(),
            'dia_vencimento' => $contratoParaAtualizar->dia_vencimento,
            'valor_aluguel' => (string) $contratoParaAtualizar->valor_aluguel,
        ];

        $response = $this->putJson("/api/contratos/{$contratoParaAtualizar->id}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['imovel_id']);
    }
}
