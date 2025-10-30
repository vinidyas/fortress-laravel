<?php

namespace App\Domain\Financeiro\Services\Reconciliation;

use App\Models\BankStatementLine;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Auth;

class IgnoreBankStatementLineService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function handle(BankStatementLine $line, ?string $reason = null): BankStatementLine
    {
        return $this->database->transaction(function () use ($line, $reason) {
            $line->update([
                'match_status' => 'ignorado',
                'matched_installment_id' => null,
                'matched_by' => Auth::user()?->getKey(),
                'match_meta' => array_merge($line->match_meta ?? [], [
                    'ignored_at' => now()->toIso8601String(),
                    'ignored_reason' => $reason,
                ]),
            ]);

            $this->updateStatementStatus($line->bank_statement_id);

            return $line->fresh();
        });
    }

    private function updateStatementStatus(int $statementId): void
    {
        $pending = $this->database->table('bank_statement_lines')
            ->where('bank_statement_id', $statementId)
            ->whereIn('match_status', ['nao_casado', 'sugerido'])
            ->exists();

        if (! $pending) {
            $this->database->table('bank_statements')
                ->where('id', $statementId)
                ->update([
                    'status' => 'conciliado',
                    'updated_at' => now(),
                ]);
        }
    }
}
