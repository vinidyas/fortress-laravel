<?php

namespace App\Http\Resources\Financeiro;

use App\Domain\Financeiro\Support\BankStatementLineStatus;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankStatementLineResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $matchStatusValue = $this->match_status ? (string) $this->match_status : null;
        $matchStatusEnum = $matchStatusValue ? BankStatementLineStatus::tryFrom($matchStatusValue) : null;
        $amount = (float) $this->amount;
        $direction = $amount >= 0 ? 'credit' : 'debit';
        $amountAbs = abs($amount);

        return [
            'id' => $this->id,
            'linha' => $this->linha,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'transaction_date_formatted' => $this->transaction_date?->format('d/m/Y'),
            'description' => $this->description,
            'amount' => $this->amount,
            'amount_abs' => round($amountAbs, 2),
            'direction' => $direction,
            'is_credit' => $direction === 'credit',
            'is_debit' => $direction === 'debit',
            'balance' => $this->balance,
            'document_number' => $this->document_number,
            'fit_id' => $this->fit_id,
            'match_status' => $this->match_status,
            'match_status_code' => $this->match_status,
            'match_status_label' => $matchStatusEnum?->label() ?? ($matchStatusValue ? ucfirst(str_replace('_', ' ', $matchStatusValue)) : null),
            'match_status_category' => $matchStatusEnum?->category(),
            'match_meta' => $this->match_meta ?? [],
            'matched_installment' => $this->whenLoaded('matchedInstallment', function () {
                $installment = $this->matchedInstallment;
                $entry = $installment->relationLoaded('journalEntry') ? $installment->journalEntry : null;
                $typeEnum = $entry && $entry->type ? JournalEntryType::tryFrom((string) $entry->type) : null;
                $statusEnum = $installment->status ? JournalEntryStatus::tryFrom((string) $installment->status) : null;

                return [
                    'id' => $installment->id,
                    'journal_entry_id' => $installment->journal_entry_id,
                    'numero_parcela' => $installment->numero_parcela,
                    'valor_total' => $installment->valor_total,
                    'status' => $installment->status,
                    'status_label' => $statusEnum
                        ? $statusEnum->label($typeEnum)
                        : ($installment->status ? ucfirst($installment->status) : null),
                ];
            }),
            'journal_entry' => $this->whenLoaded('matchedInstallment.journalEntry', function () {
                $entry = $this->matchedInstallment->journalEntry;

                return [
                    'id' => $entry->id,
                    'description' => $entry->description_custom,
                    'status' => $entry->status,
                    'type' => $entry->type,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
