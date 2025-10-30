<?php

namespace Tests\Feature\Reports;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportFinanceiroTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_autorizado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/reports/financeiro')->assertForbidden();
    }

    public function test_listagem_totais(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.financeiro']]);
        Sanctum::actingAs($user);

        JournalEntry::factory()->create([
            'type' => 'receita',
            'status' => 'pago',
            'amount' => 100,
        ]);

        JournalEntry::factory()->create([
            'type' => 'despesa',
            'status' => 'pago',
            'amount' => 40,
        ]);

        JournalEntry::factory()->create([
            'type' => 'despesa',
            'status' => 'atrasado',
            'amount' => 25,
        ]);

        $response = $this->getJson('/api/reports/financeiro');

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('totals.receitas'));
        $this->assertEquals(40.0, $response->json('totals.despesas'));
        $this->assertEquals(60.0, $response->json('totals.saldo'));
        $this->assertEquals(25.0, $response->json('totals.em_aberto'));
        $this->assertEquals(140.0, $response->json('totals.quitado'));
        $this->assertEquals(0.0, $response->json('totals.cancelado'));
    }

    public function test_filtra_por_conta(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.financeiro']]);
        Sanctum::actingAs($user);

        $accountA = FinancialAccount::factory()->create(['nome' => 'Conta A']);
        $accountB = FinancialAccount::factory()->create(['nome' => 'Conta B']);

        JournalEntry::factory()->for($accountA, 'bankAccount')->create([
            'type' => 'receita',
            'status' => 'pago',
            'amount' => 200,
        ]);

        JournalEntry::factory()->for($accountB, 'bankAccount')->create([
            'type' => 'receita',
            'status' => 'pago',
            'amount' => 500,
        ]);

        $response = $this->getJson('/api/reports/financeiro?account_id='.$accountA->id);

        $response->assertOk();
        $this->assertEquals(200.0, $response->json('totals.receitas'));
        $this->assertEquals(0.0, $response->json('totals.despesas'));
    }

    public function test_export_requer_permission(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.financeiro']]);
        Sanctum::actingAs($user);

        $this->get('/api/reports/financeiro/export')->assertForbidden();
    }

    public function test_export_csv_sucesso(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.financeiro', 'reports.export']]);
        Sanctum::actingAs($user);

        $account = FinancialAccount::factory()->create(['nome' => 'Conta Principal']);
        JournalEntry::factory()->for($account, 'bankAccount')->create([
            'type' => 'receita',
            'status' => 'pago',
            'amount' => 150.75,
        ]);

        $response = $this->get('/api/reports/financeiro/export?account_id='.$account->id);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Conta Principal', $csv);
        $this->assertStringContainsString('150.75', $csv);
    }
}
