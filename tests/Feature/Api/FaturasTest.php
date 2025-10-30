<?php

namespace Tests\Feature\Api;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaturasTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFaturasApiUser(array $permissions = []): User
    {
        $user = User::factory()->create([
            'permissoes' => $permissions,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_lists_faturas_with_filters(): void
    {
        $this->actingAsFaturasApiUser(['faturas.view']);

        Fatura::factory()->count(2)->create();

        $response = $this->getJson('/api/faturas');

        $response->assertOk()->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }

    public function test_creates_fatura_successfully(): void
    {
        $this->actingAsFaturasApiUser(['faturas.create', 'faturas.view']);

        $contrato = Contrato::factory()->create([
            'status' => 'Ativo',
            'dia_vencimento' => 10,
            'valor_aluguel' => 1500,
        ]);

        $payload = [
            'contrato_id' => $contrato->id,
            'competencia' => '2025-09',
            'itens' => [
                ['categoria' => 'Aluguel', 'quantidade' => 1, 'valor_unitario' => 1500],
            ],
        ];

        $response = $this->postJson('/api/faturas', $payload);

        $response->assertCreated();

        $this->assertTrue(
            Fatura::query()
                ->where('contrato_id', $contrato->id)
                ->whereDate('competencia', '2025-09-01')
                ->exists(),
            'Fatura nao encontrada na competencia esperada.'
        );
    }

    public function test_prevents_duplicate_fatura_for_same_competencia(): void
    {
        $this->actingAsFaturasApiUser(['faturas.create']);

        $contrato = Contrato::factory()->create(['status' => 'Ativo']);

        Fatura::factory()->create([
            'contrato_id' => $contrato->id,
            'competencia' => '2025-09-01',
        ]);

        $payload = [
            'contrato_id' => $contrato->id,
            'competencia' => '2025-09',
        ];

        $response = $this->postJson('/api/faturas', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['competencia']);
    }

    public function test_settles_a_fatura(): void
    {
        $this->actingAsFaturasApiUser(['faturas.view', 'faturas.settle']);

        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
            'valor_total' => 2000,
        ]);

        $payload = [
            'valor_pago' => '2000.00',
            'pago_em' => now()->toDateString(),
            'metodo_pagamento' => 'PIX',
        ];

        $response = $this->postJson("/api/faturas/{$fatura->id}/settle", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('faturas', [
            'id' => $fatura->id,
            'status' => 'Paga',
            'valor_pago' => 2000,
        ]);
    }

    public function test_cancels_a_fatura(): void
    {
        $this->actingAsFaturasApiUser(['faturas.view', 'faturas.cancel']);

        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
        ]);

        $response = $this->postJson("/api/faturas/{$fatura->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('faturas', [
            'id' => $fatura->id,
            'status' => 'Cancelada',
        ]);
    }

    public function test_does_not_allow_deleting_paid_fatura(): void
    {
        $this->actingAsFaturasApiUser(['faturas.delete']);

        $fatura = Fatura::factory()->create([
            'status' => 'Paga',
        ]);

        $response = $this->deleteJson("/api/faturas/{$fatura->id}");

        $response->assertStatus(409);
    }

    public function test_remove_itens_da_fatura_no_update(): void
    {
        $this->actingAsFaturasApiUser(['faturas.update']);

        $fatura = Fatura::factory()->create([
            'status' => 'Aberta',
            'valor_total' => 300,
        ]);

        $fatura->itens()->createMany([
            [
                'categoria' => 'Aluguel',
                'quantidade' => 1,
                'valor_unitario' => 200,
            ],
            [
                'categoria' => 'Condominio',
                'quantidade' => 1,
                'valor_unitario' => 100,
            ],
        ]);

        $fatura->recalcTotals()->save();

        $response = $this->putJson("/api/faturas/{$fatura->id}", [
            'itens' => [],
        ]);

        $response->assertOk();

        $fatura->refresh();

        $this->assertSame(0.0, (float) $fatura->valor_total);
        $this->assertCount(0, $fatura->itens);
    }
}
