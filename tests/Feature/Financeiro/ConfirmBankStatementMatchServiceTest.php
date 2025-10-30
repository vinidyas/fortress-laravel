<?php

namespace Tests\Feature\Financeiro;

use App\Domain\Financeiro\Services\Reconciliation\ConfirmBankStatementMatchService;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ConfirmBankStatementMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_match_marks_installment_as_paid(): void
    {
        $account = FinancialAccount::factory()->create();
        $entry = JournalEntry::factory()->for($account, 'bankAccount')->create([
            'amount' => 500.00,
            'movement_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
        ]);
        /** @var JournalEntryInstallment $installment */
        $installment = $entry->installments()->first();

        $statement = BankStatement::query()->create([
            'financial_account_id' => $account->id,
            'reference' => 'TEST-002',
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
            'description' => 'Pagamento',
            'amount' => 500.00,
            'balance' => null,
            'document_number' => null,
            'fit_id' => null,
            'match_status' => 'nao_casado',
            'match_meta' => null,
        ]);

        $user = User::factory()->create();
        Auth::login($user);

        /** @var ConfirmBankStatementMatchService $service */
        $service = app(ConfirmBankStatementMatchService::class);
        $service->handle($line, $installment, now()->toDateString());

        $line->refresh();
        $installment->refresh();

        $this->assertEquals('confirmado', $line->match_status);
        $this->assertEquals($installment->id, $line->matched_installment_id);
        $this->assertEquals('pago', $installment->status);
    }
}
