<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'installment_id',
        'number',
        'issue_date',
        'issued_by',
        'pdf_path',
        'metadata',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'metadata' => 'array',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(JournalEntryInstallment::class, 'installment_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
