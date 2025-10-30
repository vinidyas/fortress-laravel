<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_statement_line_id',
        'installment_id',
        'journal_entry_id',
        'matched_at',
        'matched_by',
        'confidence',
        'notes',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'confidence' => 'decimal:2',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(JournalEntryInstallment::class, 'installment_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
