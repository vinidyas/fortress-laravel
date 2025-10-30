<?php

namespace Tests\Feature\Financeiro;

use App\Domain\Financeiro\Services\Reconciliation\CloseReconciliationService;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use App\Models\FinancialReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_reconciliation_when_all_lines_are_resolved(): void
    {
        $account = FinancialAccount::factory()->create([
            'saldo_atual' => 0,
        ]);

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => 'TEST-003',
            'original_name' => 'file.csv',
            'imported_at' => now(),
            'imported_by' => null,
            'hash' => 'hash-'.uniqid(),
            'status' => 'conciliado',
            'meta' => [],
        ]);

        BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'linha' => 1,
            'transaction_date' => now()->toDateString(),
            'description' => 'Item',
            'amount' => 100,
            'balance' => null,
            'document_number' => null,
            'fit_id' => null,
            'match_status' => 'confirmado',
            'match_meta' => [],
        ]);

        /** @var CloseReconciliationService $service */
        $service = app(CloseReconciliationService::class);

        $reconciliation = $service->handle(
            financialAccountId: $account->id,
            periodStart: now()->startOfMonth()->toDateString(),
            periodEnd: now()->endOfMonth()->toDateString(),
            openingBalance: 0,
            closingBalance: 100,
            statementIds: [$statement->id],
        );

        $this->assertInstanceOf(FinancialReconciliation::class, $reconciliation);
        $this->assertSame('fechado', $reconciliation->status);
        $this->assertEquals(100.0, (float) $reconciliation->closing_balance);
        $this->assertEquals(100.0, (float) $account->fresh()->saldo_atual);
    }
}
