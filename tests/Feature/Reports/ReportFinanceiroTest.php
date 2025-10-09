<?php

namespace Tests\Feature\Reports;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
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

        FinancialTransaction::factory()->create(['tipo' => 'credito', 'valor' => 100]);
        FinancialTransaction::factory()->create(['tipo' => 'debito', 'valor' => 40]);

        $response = $this->getJson('/api/reports/financeiro');

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('totals.receitas'));
        $this->assertEquals(40.0, $response->json('totals.despesas'));
        $this->assertEquals(60.0, $response->json('totals.saldo'));
    }

    public function test_filtra_por_conta(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.financeiro']]);
        Sanctum::actingAs($user);

        $accountA = FinancialAccount::factory()->create(['nome' => 'Conta A']);
        $accountB = FinancialAccount::factory()->create(['nome' => 'Conta B']);

        FinancialTransaction::factory()->for($accountA, 'account')->create(['tipo' => 'credito', 'valor' => 200]);
        FinancialTransaction::factory()->for($accountB, 'account')->create(['tipo' => 'credito', 'valor' => 500]);

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
        FinancialTransaction::factory()->for($account, 'account')->create(['tipo' => 'credito', 'valor' => 150.75]);

        $response = $this->get('/api/reports/financeiro/export?account_id='.$account->id);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Conta Principal', $csv);
        $this->assertStringContainsString('150.75', $csv);
    }
}
