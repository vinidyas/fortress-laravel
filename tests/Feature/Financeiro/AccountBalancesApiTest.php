<?php

namespace Tests\Feature\Financeiro;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountBalancesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--ansi' => false]);
    }

    public function test_requires_permission(): void
    {
        $user = User::factory()->create([
            'permissoes' => [],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/financeiro/account-balances');

        $response->assertForbidden();
    }

    public function test_returns_balance_payload(): void
    {
        FinancialAccount::factory()->create([
            'nome' => 'Conta Operacional',
            'saldo_inicial' => 500,
            'saldo_atual' => 750,
        ]);

        $user = User::factory()->create([
            'permissoes' => ['financeiro.balance.view'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/financeiro/account-balances');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total_current', 750);
        $response->assertJsonPath('data.summary.total_projected', 750);
        $response->assertJsonCount(1, 'data.accounts');
    }
}
