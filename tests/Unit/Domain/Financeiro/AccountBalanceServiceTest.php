<?php

namespace Tests\Unit\Domain\Financeiro;

use App\Domain\Financeiro\Services\AccountBalanceService;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--ansi' => false]);
    }

    public function test_it_calculates_balances_and_alerts(): void
    {
        $service = app(AccountBalanceService::class);

        /** @var FinancialAccount $accountA */
        $accountA = FinancialAccount::factory()->create([
            'nome' => 'Conta Principal',
            'saldo_inicial' => 1000,
            'saldo_atual' => 1500,
            'integra_config' => [
                'alerts' => [
                    'low_balance_threshold' => 200,
                ],
            ],
        ]);

        /** @var FinancialAccount $accountB */
        $accountB = FinancialAccount::factory()->create([
            'nome' => 'Conta Pagamentos',
            'saldo_inicial' => 500,
            'saldo_atual' => -200,
            'integra_config' => [
                'alerts' => [
                    'low_balance_threshold' => -100,
                ],
            ],
        ]);

        JournalEntry::factory()->create([
            'type' => 'receita',
            'bank_account_id' => $accountA->id,
            'status' => 'pendente',
            'amount' => 300,
        ]);

        JournalEntry::factory()->create([
            'type' => 'despesa',
            'bank_account_id' => $accountB->id,
            'status' => 'pendente',
            'amount' => 100,
        ]);

        JournalEntry::factory()->create([
            'type' => 'transferencia',
            'bank_account_id' => $accountA->id,
            'counter_bank_account_id' => $accountB->id,
            'status' => 'pendente',
            'amount' => 50,
        ]);

        $user = User::factory()->create([
            'permissoes' => ['financeiro.view'],
        ]);

        $result = $service->getSummary($user, []);

        $this->assertSame('positive', $result['summary']['status']);
        $this->assertSame(1300.0, $result['summary']['total_current']);
        $this->assertSame(1500.0, $result['summary']['total_projected']);
        $this->assertSame(200.0, $result['summary']['pending_delta']);

        $this->assertCount(2, $result['accounts']);

        $principal = collect($result['accounts'])->firstWhere('id', $accountA->id);
        $this->assertNotNull($principal);
        $this->assertSame(1500.0, $principal['saldo_atual']);
        $this->assertSame(1750.0, $principal['saldo_projetado']);
        $this->assertSame(250.0, $principal['pendente_delta']);
        $this->assertFalse($principal['alerta']['ativo']);

        $pagamentos = collect($result['accounts'])->firstWhere('id', $accountB->id);
        $this->assertNotNull($pagamentos);
        $this->assertSame(-200.0, $pagamentos['saldo_atual']);
        $this->assertSame(-250.0, $pagamentos['saldo_projetado']);
        $this->assertSame(-50.0, $pagamentos['pendente_delta']);
        $this->assertTrue($pagamentos['alerta']['ativo']);

        $this->assertCount(1, $result['alerts']);
        $alert = $result['alerts'][0];
        $this->assertSame($accountB->id, $alert['account_id']);
        $this->assertSame(-200.0, $alert['current_balance']);

        $this->assertDatabaseHas('dashboard_alerts', [
            'key' => sprintf('finance.balance:%d', $accountB->id),
            'resource_id' => $accountB->id,
            'category' => 'finance.balance',
            'severity' => 'danger',
            'resolved_at' => null,
        ]);

        $this->assertDatabaseMissing('dashboard_alerts', [
            'key' => sprintf('finance.balance:%d', $accountA->id),
        ]);
    }
}
