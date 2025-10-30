<?php

namespace Tests\Feature\Reports;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportBankStatementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPermissions(array $permissions = ['reports.view.financeiro']): User
    {
        return User::factory()->create(['permissoes' => $permissions]);
    }

    public function test_index_returns_summary_and_statements(): void
    {
        $user = $this->createUserWithPermissions();
        Sanctum::actingAs($user);

        $account = FinancialAccount::factory()->create(['nome' => 'Conta Corrente']);

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => '2024-08',
            'original_name' => 'extrato_agosto.ofx',
            'imported_at' => Carbon::parse('2024-08-31 12:00:00'),
            'imported_by' => $user->getKey(),
            'hash' => 'hash-agosto',
            'status' => 'importado',
            'meta' => [
                'opening_balance' => 500.0,
                'closing_balance' => 650.0,
            ],
        ]);

        BankStatementLine::query()->insert([
            [
                'bank_statement_id' => $statement->id,
                'linha' => 1,
                'transaction_date' => '2024-08-05',
                'description' => 'Recebimento aluguel',
                'amount' => 200.0,
                'balance' => 700.0,
                'match_status' => 'confirmado',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'bank_statement_id' => $statement->id,
                'linha' => 2,
                'transaction_date' => '2024-08-06',
                'description' => 'Pagamento fornecedor',
                'amount' => -50.0,
                'balance' => 650.0,
                'match_status' => 'sugerido',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/reports/bank-statements');

        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.statements.total'));
        $this->assertEquals(2, $response->json('summary.lines.total'));
        $this->assertEquals(200.0, $response->json('summary.totals.inflow'));
        $this->assertEquals(50.0, $response->json('summary.totals.outflow'));
        $this->assertEquals(150.0, $response->json('summary.totals.net'));

        $this->assertCount(1, $response->json('data'));
        $payload = $response->json('data.0');

        $this->assertEquals('Conta Corrente', $payload['account']['nome']);
        $this->assertEquals(200.0, $payload['totals']['inflow']);
        $this->assertEquals(50.0, $payload['totals']['outflow']);
        $this->assertEquals(150.0, $payload['totals']['net']);
        $this->assertEquals(1, $payload['counts']['confirmed']);
        $this->assertEquals(1, $payload['counts']['suggested']);
    }

    public function test_index_can_include_lines(): void
    {
        $user = $this->createUserWithPermissions();
        Sanctum::actingAs($user);

        $account = FinancialAccount::factory()->create();

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => '2024-07',
            'original_name' => 'extrato_julho.ofx',
            'imported_at' => now(),
            'imported_by' => $user->getKey(),
            'hash' => 'hash-julho',
            'status' => 'importado',
            'meta' => [],
        ]);

        BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'linha' => 1,
            'transaction_date' => now()->toDateString(),
            'description' => 'Receita teste',
            'amount' => 100,
            'balance' => 100,
            'match_status' => 'nao_casado',
        ]);

        $response = $this->getJson('/api/reports/bank-statements?with_lines=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.0.lines'));
        $this->assertEquals('Receita teste', $response->json('data.0.lines.0.description'));
    }

    public function test_export_generates_csv(): void
    {
        $user = $this->createUserWithPermissions(['reports.view.financeiro', 'reports.export']);
        Sanctum::actingAs($user);

        $account = FinancialAccount::factory()->create(['nome' => 'Conta Export']);

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => '2024-06',
            'original_name' => 'jun.ofx',
            'imported_at' => now(),
            'imported_by' => $user->getKey(),
            'hash' => 'hash-jun',
            'status' => 'conciliado',
            'meta' => ['closing_balance' => 123.45],
        ]);

        BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'linha' => 1,
            'transaction_date' => now()->toDateString(),
            'description' => 'Receita export',
            'amount' => 123.45,
            'balance' => 123.45,
            'match_status' => 'confirmado',
        ]);

        $response = $this->get('/api/reports/bank-statements/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Conta Export', $content);
        $this->assertStringContainsString('2024-06', $content);
        $this->assertStringContainsString('123.45', $content);
    }
}
