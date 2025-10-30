<?php

namespace App\Domain\Financeiro\Services\Reconciliation;

use App\Domain\Financeiro\Services\Installment\PayInstallmentService;
use App\Models\BankStatementLine;
use App\Models\BankStatementMatch;
use App\Models\JournalEntryInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Auth;

class ConfirmBankStatementMatchService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly PayInstallmentService $payInstallmentService,
    ) {
    }

    public function handle(BankStatementLine $line, JournalEntryInstallment $installment, string $paymentDate): BankStatementLine
    {
        $line->loadMissing('statement');
        $statement = $line->statement;
        $installment->loadMissing('journalEntry');

        if (! $statement) {
            throw new \RuntimeException('Extrato não encontrado para a linha informada.');
        }

        $entry = $installment->journalEntry;
        if (! $entry) {
            throw new \RuntimeException('Lançamento não encontrado para a parcela informada.');
        }

        if ($entry->bank_account_id !== $statement->financial_account_id) {
            throw new \RuntimeException('Parcela não pertence à conta financeira do extrato.');
        }

        if ($installment->status === 'pago' && $line->match_status !== 'confirmado') {
            $paymentDate = $installment->payment_date?->toDateString() ?? $paymentDate;
        }

        $paymentDate = CarbonImmutable::parse($paymentDate)->toDateString();

        return $this->database->transaction(function () use ($line, $installment, $statement, $paymentDate) {
            if ($installment->status !== 'pago') {
                $this->payInstallmentService->handle(
                    $installment,
                    paymentDate: $paymentDate,
                );
            }

            $line->update([
                'match_status' => 'confirmado',
                'matched_installment_id' => $installment->id,
                'matched_by' => Auth::user()?->getKey(),
                'match_meta' => array_merge($line->match_meta ?? [], [
                    'confirmed_at' => now()->toIso8601String(),
                ]),
            ]);

            BankStatementMatch::query()->create([
                'bank_statement_line_id' => $line->id,
                'installment_id' => $installment->id,
                'journal_entry_id' => $installment->journal_entry_id,
                'matched_at' => now(),
                'matched_by' => Auth::user()?->getKey(),
                'confidence' => $this->extractConfidence($line),
                'notes' => null,
            ]);

            $this->updateStatementStatus($statement->id);

            return $line->fresh(['matchedInstallment.journalEntry']);
        });
    }

    private function extractConfidence(BankStatementLine $line): ?float
    {
        $suggestions = $line->match_meta['suggestions'] ?? [];
        $installmentId = $line->matched_installment_id;

        foreach ($suggestions as $suggestion) {
            if (($suggestion['installment_id'] ?? null) === $installmentId) {
                return $suggestion['confidence'] ?? null;
            }
        }

        return null;
    }

    private function updateStatementStatus(int $statementId): void
    {
        $statement = $this->database->table('bank_statements')->where('id', $statementId)->first();
        if (! $statement) {
            return;
        }

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
