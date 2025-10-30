<?php

namespace App\Domain\Financeiro\Services\Reconciliation;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\JournalEntryInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class SuggestMatchesService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function handle(BankStatement $statement): BankStatement
    {
        $statement->load('lines');

        $this->database->transaction(function () use ($statement) {
            foreach ($statement->lines as $line) {
                if ($line->match_status === 'confirmado') {
                    continue;
                }

                $suggestions = $this->findSuggestions($statement, $line);

                if ($suggestions->isEmpty()) {
                    $line->update([
                        'match_status' => 'nao_casado',
                        'match_meta' => [
                            'suggestions' => [],
                        ],
                    ]);

                    continue;
                }

                $topSuggestion = $suggestions->first();
                $status = $topSuggestion['confidence'] >= 75 ? 'sugerido' : 'nao_casado';

                $line->update([
                    'match_status' => $status,
                    'match_meta' => [
                        'suggestions' => $suggestions->values()->all(),
                    ],
                ]);
            }
        });

        return $statement->fresh('lines');
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function findSuggestions(BankStatement $statement, BankStatementLine $line): Collection
    {
        $amount = (float) $line->amount;
        $absAmount = round(abs($amount), 2);
        $transactionDate = CarbonImmutable::parse($line->transaction_date);

        $candidates = JournalEntryInstallment::query()
            ->select('journal_entry_installments.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_installments.journal_entry_id')
            ->where('journal_entries.bank_account_id', $statement->financial_account_id)
            ->whereNotIn('journal_entries.status', ['cancelado'])
            ->whereIn('journal_entry_installments.status', ['planejado', 'pendente'])
            ->whereNull('journal_entry_installments.payment_date')
            ->whereRaw('ABS(journal_entry_installments.valor_total - ?) <= 0.01', [$absAmount])
            ->get()
            ->map(function (JournalEntryInstallment $installment) use ($line, $transactionDate) {
                $entry = $installment->journalEntry()->first();
                if (! $entry) {
                    return null;
                }

                $dueDate = $installment->due_date ? CarbonImmutable::parse($installment->due_date) : null;
                $movementDate = $installment->movement_date ? CarbonImmutable::parse($installment->movement_date) : null;

                $closestDate = $dueDate ?? $movementDate ?? $transactionDate;
                $daysDiff = abs($closestDate->diffInDays($transactionDate, false));
                $dateScore = max(0, 30 - min($daysDiff, 30));

                $description = mb_strtolower($line->description ?? '');
                $entryDescription = mb_strtolower($entry->description_custom ?? '');
                $descriptionScore = $description !== '' && $entryDescription !== ''
                    ? (str_contains($entryDescription, $description) || str_contains($description, $entryDescription) ? 30 : 10)
                    : 15;

                $score = 50 + $dateScore + $descriptionScore;

                if ($score > 100) {
                    $score = 100;
                }

                return [
                    'installment_id' => $installment->id,
                    'journal_entry_id' => $entry->id,
                    'confidence' => (int) round($score),
                    'journal_entry_description' => $entry->description_custom,
                    'installment_due_date' => $installment->due_date?->toDateString(),
                    'installment_number' => $installment->numero_parcela,
                ];
            })
            ->filter()
            ->sortByDesc('confidence')
            ->take(5);

        return $candidates->values();
    }
}
