<?php

namespace Tests\Feature\Financeiro;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(array $permissions): User
    {
        $user = User::factory()->create(['permissoes' => $permissions]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_nao_autorizado_retorna_403(): void
    {
        $this->actingAsUser([]);

        $response = $this->postJson('/api/financeiro/accounts', [
            'nome' => 'Conta Restrita',
            'tipo' => 'caixa',
            'saldo_inicial' => 100,
            'ativo' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_cria_conta_financeira(): void
    {
        $this->actingAsUser(['financeiro.create']);

        $payload = [
            'nome' => 'Conta Principal',
            'tipo' => 'conta_corrente',
            'saldo_inicial' => 2500.75,
            'ativo' => true,
        ];

        $response = $this->postJson('/api/financeiro/accounts', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.nome', 'Conta Principal');
        $this->assertDatabaseHas('financial_accounts', [
            'nome' => 'Conta Principal',
            'tipo' => 'conta_corrente',
            'saldo_inicial' => 2500.75,
        ]);
    }
}
