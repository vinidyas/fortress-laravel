<?php

namespace App\Http\Resources\Financeiro;

use App\Domain\Financeiro\Support\BankStatementStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankStatementResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = $this->status ? (string) $this->status : null;
        $statusEnum = $statusValue ? BankStatementStatus::tryFrom($statusValue) : null;
        $lines = $this->whenLoaded('lines') ? $this->lines : null;

        $totalLines = $this->total_lines_count ?? ($lines ? $lines->count() : null);
        $pendingLines = $this->pending_lines_count ?? ($lines ? $lines->whereIn('match_status', ['nao_casado', 'sugerido'])->count() : null);
        $confirmedLines = $this->confirmed_lines_count ?? ($lines ? $lines->where('match_status', 'confirmado')->count() : null);
        $suggestedLines = $this->suggested_lines_count ?? ($lines ? $lines->where('match_status', 'sugerido')->count() : null);
        $ignoredLines = $this->ignored_lines_count ?? ($lines ? $lines->where('match_status', 'ignorado')->count() : null);

        $creditSum = $this->credit_sum_amount ?? ($lines ? (float) $lines->where('amount', '>', 0)->sum('amount') : 0.0);
        $debitSum = $this->debit_sum_amount ?? ($lines ? (float) $lines->where('amount', '<', 0)->sum('amount') : 0.0);
        $totalSum = $this->total_sum_amount ?? ($lines ? (float) $lines->sum('amount') : 0.0);

        $meta = $this->meta ?? [];

        return [
            'id' => $this->id,
            'financial_account_id' => $this->financial_account_id,
            'reference' => $this->reference,
            'original_name' => $this->original_name,
            'imported_at' => $this->imported_at?->toIso8601String(),
            'imported_at_formatted' => $this->imported_at?->format('d/m/Y H:i'),
            'status' => $this->status,
            'status_code' => $this->status,
            'status_label' => $statusEnum?->label() ?? ($statusValue ? ucfirst($statusValue) : null),
            'status_category' => $statusEnum?->category(),
            'meta' => $meta,
            'balances' => [
                'opening' => isset($meta['opening_balance']) ? (float) $meta['opening_balance'] : null,
                'closing' => isset($meta['closing_balance']) ? (float) $meta['closing_balance'] : null,
            ],
            'account' => $this->whenLoaded('account', fn () => [
                'id' => $this->account->id,
                'nome' => $this->account->nome,
            ]),
            'imported_by' => $this->whenLoaded('importedByUser', fn () => [
                'id' => $this->importedByUser->id,
                'name' => $this->importedByUser->nome ?? $this->importedByUser->username,
            ]),
            'counts' => [
                'total' => $totalLines !== null ? (int) $totalLines : null,
                'pending' => $pendingLines !== null ? (int) $pendingLines : null,
                'confirmed' => $confirmedLines !== null ? (int) $confirmedLines : null,
                'suggested' => $suggestedLines !== null ? (int) $suggestedLines : null,
                'ignored' => $ignoredLines !== null ? (int) $ignoredLines : null,
            ],
            'totals' => [
                'inflow' => round((float) $creditSum, 2),
                'outflow' => round(abs((float) $debitSum), 2),
                'net' => round((float) $totalSum, 2),
            ],
            'lines' => BankStatementLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
