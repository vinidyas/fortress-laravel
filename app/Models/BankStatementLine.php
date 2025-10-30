<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_statement_id',
        'linha',
        'transaction_date',
        'description',
        'amount',
        'balance',
        'document_number',
        'fit_id',
        'match_status',
        'matched_installment_id',
        'matched_by',
        'match_meta',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'match_meta' => 'array',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function matchedInstallment(): BelongsTo
    {
        return $this->belongsTo(JournalEntryInstallment::class, 'matched_installment_id');
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankStatementMatch::class);
    }
}
