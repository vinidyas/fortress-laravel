<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntryInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'numero_parcela',
        'movement_date',
        'due_date',
        'payment_date',
        'valor_principal',
        'valor_juros',
        'valor_multa',
        'valor_desconto',
        'valor_total',
        'status',
        'paid_by_installment_id',
        'meta',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'valor_principal' => 'decimal:2',
        'valor_juros' => 'decimal:2',
        'valor_multa' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'meta' => 'array',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paid_by_installment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(JournalEntryAttachment::class, 'installment_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(FinancialReceipt::class, 'installment_id');
    }
}
