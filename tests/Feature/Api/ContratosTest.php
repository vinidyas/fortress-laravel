<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ContratoGarantiaTipo;
use App\Enums\ContratoReajusteIndice;
use App\Enums\ContratoStatus;
use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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

    public function test_generate_codigo_endpoint_returns_code(): void
    {
        $this->actingAsContratoApiUser(['contratos.create']);

        $response = $this->getJson('/api/contratos/generate-codigo');

        $response->assertOk()->assertJsonStructure(['codigo']);

        $codigo = $response->json('codigo');

        $this->assertIsString($codigo);
        $this->assertNotSame('', $codigo);
        $this->assertMatchesRegularExpression('/^CTR-\d{5}$/', $codigo);
    }

    public function test_creates_contrato_with_fiadores_and_attachments(): void
    {
        Storage::fake('public');

        $user = $this->actingAsContratoApiUser(['contratos.create', 'contratos.view']);

        $imovel = Imovel::factory()->create();
        $locador = Pessoa::factory()->create();
        $locatario = Pessoa::factory()->create();
        $fiadorA = Pessoa::factory()->create();
        $fiadorB = Pessoa::factory()->create();

        $payload = $this->validPayload([
            'codigo_contrato' => 'CTR-001',
            'imovel_id' => $imovel->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'fiadores' => [$fiadorA->id, $fiadorB->id],
            'anexos' => [UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf')],
        ]);

        $response = $this->post('/api/contratos', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('contratos', [
            'codigo_contrato' => 'CTR-001',
            'multa_rescisao_alugueis' => 2.5,
            'reajuste_teto_percentual' => 8.5,
            'garantia_tipo' => ContratoGarantiaTipo::Caucao->value,
            'repasse_automatico' => true,
        ]);

        $contratoId = $response->json('data.id');

        $this->assertDatabaseHas('contrato_fiadores', [
            'contrato_id' => $contratoId,
            'pessoa_id' => $fiadorA->id,
        ]);

        Storage::disk('public')->assertExists("contratos/{$contratoId}");
    }

    public function test_prevents_duplicate_codigo_contrato(): void
    {
        $this->actingAsContratoApiUser(['contratos.create']);

        $contrato = Contrato::factory()->create(['codigo_contrato' => 'CTR-001']);

        $payload = $this->validPayload([
            'codigo_contrato' => 'CTR-001',
            'imovel_id' => $contrato->imovel_id,
            'locador_id' => $contrato->locador_id,
            'locatario_id' => $contrato->locatario_id,
        ]);

        $response = $this->postJson('/api/contratos', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['codigo_contrato']);
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
            'status' => ContratoStatus::Ativo->value,
            'data_inicio' => Carbon::now()->subMonths(1)->toDateString(),
            'data_fim' => Carbon::now()->addMonths(11)->toDateString(),
        ]);

        $payload = $this->validPayload([
            'codigo_contrato' => 'CTR-XYZ',
            'imovel_id' => $imovel->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
        ]);

        $response = $this->postJson('/api/contratos', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['imovel_id']);
    }

    public function test_requires_caucao_value_when_garantia_is_caucao(): void
    {
        $this->actingAsContratoApiUser(['contratos.create']);

        $imovel = Imovel::factory()->create();
        $locador = Pessoa::factory()->create();
        $locatario = Pessoa::factory()->create();

        $payload = $this->validPayload([
            'imovel_id' => $imovel->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'garantia_tipo' => ContratoGarantiaTipo::Caucao->value,
            'caucao_valor' => null,
        ]);

        $response = $this->postJson('/api/contratos', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['caucao_valor']);
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
            'status' => ContratoStatus::Ativo->value,
            'data_inicio' => Carbon::now()->subMonths(1)->toDateString(),
            'data_fim' => null,
        ]);

        $contratoParaAtualizar = Contrato::factory()->create([
            'imovel_id' => $imovelB->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'status' => ContratoStatus::Ativo->value,
            'data_inicio' => Carbon::now()->subMonths(2)->toDateString(),
            'data_fim' => null,
        ]);

        $payload = $this->validPayload([
            'codigo_contrato' => $contratoParaAtualizar->codigo_contrato,
            'imovel_id' => $imovelA->id,
            'locador_id' => $contratoParaAtualizar->locador_id,
            'locatario_id' => $contratoParaAtualizar->locatario_id,
        ]);

        $response = $this->putJson("/api/contratos/{$contratoParaAtualizar->id}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['imovel_id']);
    }

    public function test_delete_contrato_with_faturas_returns_conflict(): void
    {
        $this->actingAsContratoApiUser(['contratos.delete']);

        $contrato = Contrato::factory()->create();

        // Cria uma fatura vinculada ao contrato
        \App\Models\Fatura::query()->create([
            'contrato_id' => $contrato->id,
            'competencia' => now()->startOfMonth()->toDateString(),
            'vencimento' => now()->startOfMonth()->addDays(max(1, min(28, $contrato->dia_vencimento ?? 1)))->toDateString(),
            'status' => 'Aberta',
        ]);

        $response = $this->deleteJson("/api/contratos/{$contrato->id}");

        $response->assertStatus(409);
        $response->assertJson([
            'message' => 'Não é possível excluir o contrato: existem faturas vinculadas.'
        ]);

        // Garante que o contrato ainda existe
        $this->assertDatabaseHas('contratos', [
            'id' => $contrato->id,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        $base = [
            'codigo_contrato' => strtoupper(fake()->bothify('CTR-#####')),
            'imovel_id' => Imovel::factory()->create()->id,
            'locador_id' => Pessoa::factory()->create()->id,
            'locatario_id' => Pessoa::factory()->create()->id,
            'data_inicio' => now()->toDateString(),
            'data_fim' => now()->addMonths(12)->toDateString(),
            'dia_vencimento' => 10,
            'carencia_meses' => 0,
            'valor_aluguel' => '2500.00',
            'reajuste_indice' => ContratoReajusteIndice::IGPM->value,
            'reajuste_periodicidade_meses' => 12,
            'data_proximo_reajuste' => now()->addYear()->toDateString(),
            'reajuste_teto_percentual' => '8.5',
            'garantia_tipo' => ContratoGarantiaTipo::Caucao->value,
            'caucao_valor' => '1000.00',
            'multa_atraso_percentual' => '2.00',
            'juros_mora_percentual_mes' => '1.00',
            'multa_rescisao_alugueis' => '2.5',
            'repasse_automatico' => true,
            'conta_cobranca_id' => null,
            'forma_pagamento_preferida' => 'Boleto',
            'tipo_contrato' => 'Residencial',
            'status' => ContratoStatus::Ativo->value,
            'observacoes' => 'Contrato gerado em testes.',
        ];

        return array_merge($base, $overrides);
    }
}
