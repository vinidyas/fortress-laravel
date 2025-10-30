<?php

namespace Tests\Feature\Financeiro;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankStatementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_statements_returns_aggregates(): void
    {
        $user = User::factory()->create(['permissoes' => ['financeiro.view']]);
        Sanctum::actingAs($user);

        $account = FinancialAccount::factory()->create();

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => 'AGO-2024',
            'original_name' => 'extrato_ofx.ofx',
            'imported_at' => Carbon::now(),
            'imported_by' => $user->id,
            'hash' => 'hash-123',
            'status' => 'importado',
            'meta' => [
                'opening_balance' => 1000.0,
                'closing_balance' => 1100.0,
            ],
        ]);

        BankStatementLine::query()->insert([
            [
                'bank_statement_id' => $statement->id,
                'linha' => 1,
                'transaction_date' => Carbon::now()->toDateString(),
                'description' => 'Recebimento',
                'amount' => 100.0,
                'balance' => 1100.0,
                'match_status' => 'confirmado',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'bank_statement_id' => $statement->id,
                'linha' => 2,
                'transaction_date' => Carbon::now()->toDateString(),
                'description' => 'Pagamento fornecedor',
                'amount' => -40.0,
                'balance' => 1060.0,
                'match_status' => 'sugerido',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'bank_statement_id' => $statement->id,
                'linha' => 3,
                'transaction_date' => Carbon::now()->toDateString(),
                'description' => 'Tarifa bancária',
                'amount' => -10.0,
                'balance' => 1050.0,
                'match_status' => 'nao_casado',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'bank_statement_id' => $statement->id,
                'linha' => 4,
                'transaction_date' => Carbon::now()->toDateString(),
                'description' => 'Ajuste manual',
                'amount' => 50.0,
                'balance' => 1100.0,
                'match_status' => 'ignorado',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/financeiro/bank-statements');

        $response->assertOk();
        $data = $response->json('data.0');

        $this->assertNotNull($data);
        $this->assertEquals('Importado', $data['status_label']);
        $this->assertEquals('open', $data['status_category']);
        $this->assertEquals(4, $data['counts']['total']);
        $this->assertEquals(2, $data['counts']['pending']);
        $this->assertEquals(1, $data['counts']['confirmed']);
        $this->assertEquals(1, $data['counts']['suggested']);
        $this->assertEquals(1, $data['counts']['ignored']);
        $this->assertEquals(150.0, $data['totals']['inflow']);
        $this->assertEquals(50.0, $data['totals']['outflow']);
        $this->assertEquals(100.0, $data['totals']['net']);
        $this->assertEquals(1000.0, $data['balances']['opening']);
        $this->assertEquals(1100.0, $data['balances']['closing']);
        $this->assertEquals($user->id, $data['imported_by']['id']);
    }

    public function test_index_filter_by_group_status(): void
    {
        $user = User::factory()->create(['permissoes' => ['financeiro.view']]);
        Sanctum::actingAs($user);

        $account = FinancialAccount::factory()->create();

        BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => 'JUL-2024',
            'original_name' => 'jul.ofx',
            'imported_at' => Carbon::now(),
            'imported_by' => $user->id,
            'hash' => 'hash-1',
            'status' => 'importado',
            'meta' => [],
        ]);

        BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => 'JUN-2024',
            'original_name' => 'jun.ofx',
            'imported_at' => Carbon::now()->subMonth(),
            'imported_by' => $user->id,
            'hash' => 'hash-2',
            'status' => 'conciliado',
            'meta' => [],
        ]);

        $response = $this->getJson('/api/financeiro/bank-statements?status=open');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('JUL-2024', $response->json('data.0.reference'));

        $responseClosed = $this->getJson('/api/financeiro/bank-statements?status=conciliado');
        $responseClosed->assertOk();
        $this->assertCount(1, $responseClosed->json('data'));
        $this->assertEquals('JUN-2024', $responseClosed->json('data.0.reference'));
    }

    public function test_show_returns_lines_with_labels(): void
    {
        $user = User::factory()->create(['permissoes' => ['financeiro.view']]);
        Sanctum::actingAs($user);

        $account = FinancialAccount::factory()->create();

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => 'MAI-2024',
            'original_name' => 'mai.ofx',
            'imported_at' => Carbon::now(),
            'imported_by' => $user->id,
            'hash' => 'hash-3',
            'status' => 'importado',
            'meta' => [],
        ]);

        $line = BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'linha' => 1,
            'transaction_date' => Carbon::now()->toDateString(),
            'description' => 'Recebimento aluguel',
            'amount' => 250.0,
            'balance' => 1250.0,
            'match_status' => 'sugerido',
        ]);

        $response = $this->getJson(sprintf('/api/financeiro/bank-statements/%d', $statement->id));

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals('Importado', $data['status_label']);
        $this->assertEquals('open', $data['status_category']);
        $this->assertArrayHasKey('lines', $data);
        $this->assertCount(1, $data['lines']);

        $linePayload = $data['lines'][0];
        $this->assertEquals($line->id, $linePayload['id']);
        $this->assertEquals('Sugestão', $linePayload['match_status_label']);
        $this->assertEquals('suggested', $linePayload['match_status_category']);
        $this->assertEquals('credit', $linePayload['direction']);
        $this->assertEquals(250.0, $linePayload['amount']);
        $this->assertEquals(250.0, $linePayload['amount_abs']);
    }
}
