<?php

namespace Tests\Feature\Reports;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportBankLedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.financeiro', 'reports.export']]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_index_returns_ledger_with_balances(): void
    {
        $this->actingUser();

        $account = FinancialAccount::factory()->create(['nome' => 'Conta Ledger']);

        JournalEntry::factory()->create([
            'bank_account_id' => $account->id,
            'movement_date' => Carbon::parse('2024-01-10'),
            'type' => 'receita',
            'amount' => 200,
            'status' => 'pago',
        ]);

        JournalEntry::factory()->create([
            'bank_account_id' => $account->id,
            'movement_date' => Carbon::parse('2024-02-10'),
            'type' => 'despesa',
            'amount' => 80,
            'status' => 'pago',
        ]);

        $response = $this->getJson('/api/reports/bank-ledger?financial_account_id='.$account->id.'&date_from=2024-02-01&date_to=2024-02-28');

        $response->assertOk();
        $this->assertEquals('despesa', $response->json('data.0.type'));
        $this->assertEquals(200.0, $response->json('opening_balance'));
        $this->assertEquals(120.0, $response->json('closing_balance'));
        $this->assertEquals(0.0, $response->json('totals.inflow'));
        $this->assertEquals(80.0, $response->json('totals.outflow'));
        $this->assertEquals(120.0, $response->json('data.0.balance_after'));
    }

    public function test_index_without_account_returns_all_banks(): void
    {
        $this->actingUser();

        $firstAccount = FinancialAccount::factory()->create(['nome' => 'Primeira Conta']);
        $secondAccount = FinancialAccount::factory()->create(['nome' => 'Segunda Conta']);

        JournalEntry::factory()->create([
            'bank_account_id' => $firstAccount->id,
            'movement_date' => Carbon::parse('2024-05-10'),
            'type' => 'despesa',
            'amount' => 120,
            'status' => 'pago',
        ]);

        JournalEntry::factory()->create([
            'bank_account_id' => $secondAccount->id,
            'movement_date' => Carbon::parse('2024-05-12'),
            'type' => 'despesa',
            'amount' => 80,
            'status' => 'pago',
        ]);

        $response = $this->getJson('/api/reports/bank-ledger?type=despesa');

        $response->assertOk();
        $this->assertSame('Todos os bancos', $response->json('account.nome'));
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(0.0, $response->json('opening_balance'));
        $this->assertEquals(-200.0, $response->json('closing_balance'));
        $this->assertEquals(0.0, $response->json('totals.inflow'));
        $this->assertEquals(200.0, $response->json('totals.outflow'));
    }

    public function test_export_generates_csv(): void
    {
        $this->actingUser();

        $account = FinancialAccount::factory()->create(['nome' => 'Conta Export Ledger']);

        JournalEntry::factory()->create([
            'bank_account_id' => $account->id,
            'movement_date' => Carbon::parse('2024-03-01'),
            'type' => 'receita',
            'amount' => 150,
            'status' => 'pago',
        ]);

        $response = $this->get('/api/reports/bank-ledger/export?financial_account_id='.$account->id.'&format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Conta Export Ledger', $csv);
        $this->assertStringContainsString('Fornecedor', $csv);
        $this->assertStringContainsString('150.00', $csv);
    }

    public function test_export_with_all_accounts_generates_csv(): void
    {
        $this->actingUser();

        FinancialAccount::factory()->create(['nome' => 'Primeira Conta']);

        JournalEntry::factory()->create([
            'bank_account_id' => FinancialAccount::factory()->create()->id,
            'movement_date' => Carbon::parse('2024-03-05'),
            'type' => 'despesa',
            'amount' => 90,
            'status' => 'pago',
        ]);

        $response = $this->get('/api/reports/bank-ledger/export?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Todos os bancos', $csv);
    }

    public function test_export_generates_pdf(): void
    {
        $this->actingUser();

        $account = FinancialAccount::factory()->create(['nome' => 'Conta PDF']);

        JournalEntry::factory()->create([
            'bank_account_id' => $account->id,
            'movement_date' => Carbon::parse('2024-04-01'),
            'type' => 'despesa',
            'amount' => 75,
            'status' => 'pago',
        ]);

        $response = $this->get('/api/reports/bank-ledger/export?financial_account_id='.$account->id.'&format=pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }
}
