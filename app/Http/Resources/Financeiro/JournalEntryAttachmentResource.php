<?php

namespace App\Http\Resources\Financeiro;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryAttachmentResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journal_entry_id' => $this->journal_entry_id,
            'installment_id' => $this->installment_id,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'uploaded_by' => $this->uploadedBy?->only(['id', 'name']) ?? null,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'download_url' => route('financeiro.journal-entries.attachments.download', [$this->journal_entry_id, $this->id]),
        ];
    }
}
