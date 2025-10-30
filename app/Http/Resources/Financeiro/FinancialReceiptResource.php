<?php

namespace App\Http\Resources\Financeiro;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialReceiptResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'issue_date' => $this->issue_date?->toDateString(),
            'issued_by' => $this->issuedBy?->only(['id', 'name']) ?? null,
            'installment_id' => $this->installment_id,
            'journal_entry_id' => $this->journal_entry_id,
            'status' => $this->metadata['status'] ?? ($this->pdf_path ? 'generated' : 'processing'),
            'pdf_path' => $this->pdf_path,
            'download_url' => $this->when($this->pdf_path, fn () => route('financeiro.journal-entries.receipts.download', [$this->journal_entry_id, $this->id])),
            'created_at' => $this->created_at?->toIso8601String(),
            'metadata' => $this->metadata ?? [],
        ];
    }
}
