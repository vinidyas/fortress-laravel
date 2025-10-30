<?php

namespace Tests\Feature\Financeiro;

use App\Domain\Financeiro\Services\Reconciliation\SuggestMatchesService;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestMatchesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_suggestions_for_matching_installments(): void
    {
        $account = FinancialAccount::factory()->create();
        $entry = JournalEntry::factory()->for($account, 'bankAccount')->create([
            'amount' => 250.00,
            'movement_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
        ]);
        $installment = $entry->installments()->first();

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => 'TEST-001',
            'original_name' => 'test.ofx',
            'imported_at' => now(),
            'imported_by' => null,
            'hash' => 'hash-'.uniqid(),
            'status' => 'importado',
            'meta' => [],
        ]);

        $line = BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'linha' => 1,
            'transaction_date' => now()->toDateString(),
            'description' => $entry->description_custom,
            'amount' => 250.00,
            'balance' => null,
            'document_number' => null,
            'fit_id' => null,
            'match_status' => 'nao_casado',
            'match_meta' => null,
        ]);

        /** @var SuggestMatchesService $service */
        $service = app(SuggestMatchesService::class);
        $service->handle($statement->fresh('lines'));

        $line->refresh();

        $this->assertEquals('sugerido', $line->match_status);
        $this->assertNotEmpty($line->match_meta['suggestions']);
        $this->assertSame($installment->id, $line->match_meta['suggestions'][0]['installment_id']);
    }
}
