<?php

namespace App\Domain\Financeiro\Services\Reconciliation;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use App\Models\FinancialReconciliation;
use App\Events\Financeiro\AccountBalancesShouldRefresh;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CloseReconciliationService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    /**
     * @param  array<int,int>  $statementIds
     */
    public function handle(
        int $financialAccountId,
        string $periodStart,
        string $periodEnd,
        float $openingBalance,
        float $closingBalance,
        array $statementIds = []
    ): FinancialReconciliation {
        $account = FinancialAccount::query()->findOrFail($financialAccountId);
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'period_end' => 'A data final do período deve ser posterior à inicial.',
            ]);
        }

        $existing = FinancialReconciliation::query()
            ->where('financial_account_id', $account->id)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('period_end', [$start->toDateString(), $end->toDateString()]);
            })
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'period_start' => 'Já existe uma reconciliação registrada para o período informado.',
            ]);
        }

        $statements = BankStatement::query()
            ->where('financial_account_id', $account->id)
            ->when($statementIds !== [], fn ($query) => $query->whereIn('id', $statementIds))
            ->whereBetween('imported_at', [$start, $end])
            ->get();

        foreach ($statements as $statement) {
            $pending = $statement->lines()
                ->whereIn('match_status', ['nao_casado', 'sugerido'])
                ->exists();

            if ($pending) {
                throw ValidationException::withMessages([
                    'statement_ids' => "O extrato {$statement->reference} possui lançamentos pendentes de conciliação.",
                ]);
            }
        }

        if ($statements->isNotEmpty()) {
            $confirmedTotal = (float) BankStatementLine::query()
                ->whereIn('bank_statement_id', $statements->pluck('id'))
                ->where('match_status', 'confirmado')
                ->sum('amount');

            $expectedClosing = round($openingBalance + $confirmedTotal, 2);
            if (abs($expectedClosing - $closingBalance) > 0.05) {
                throw ValidationException::withMessages([
                    'closing_balance' => sprintf(
                        'O saldo final informado (%.2f) não corresponde ao saldo esperado (%.2f) somando as movimentações confirmadas.',
                        $closingBalance,
                        $expectedClosing,
                    ),
                ]);
            }
        }

        return $this->database->transaction(function () use ($account, $start, $end, $openingBalance, $closingBalance, $statements) {
            /** @var FinancialReconciliation $reconciliation */
            $reconciliation = FinancialReconciliation::query()->create([
                'financial_account_id' => $account->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'status' => 'fechado',
                'notes' => null,
                'locked_by' => auth()->id(),
            ]);

            if ($statements->isNotEmpty()) {
                BankStatement::query()
                    ->whereIn('id', $statements->pluck('id'))
                    ->update([
                        'status' => 'conciliado',
                        'updated_at' => now(),
                    ]);
            }

            $account->update([
                'saldo_atual' => $closingBalance,
            ]);

            event(new AccountBalancesShouldRefresh([$account->id]));

            return $reconciliation;
        });
    }
}
