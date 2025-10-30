<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'bank_account_id',
        'counter_bank_account_id',
        'cost_center_id',
        'property_id',
        'person_id',
        'description_id',
        'description_custom',
        'notes',
        'reference_code',
        'origin',
        'clone_of_id',
        'movement_date',
        'due_date',
        'payment_date',
        'amount',
        'currency',
        'status',
        'installments_count',
        'paid_installments',
        'attachments_count',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'installments_count' => 'integer',
        'paid_installments' => 'integer',
        'attachments_count' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'bank_account_id');
    }

    public function counterBankAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'counter_bank_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Imovel::class, 'property_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'person_id');
    }

    public function description(): BelongsTo
    {
        return $this->belongsTo(JournalEntryDescription::class);
    }

    public function cloneOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'clone_of_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(JournalEntryInstallment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(JournalEntryAllocation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(JournalEntryAttachment::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(FinancialReceipt::class);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner->whereNull('clone_of_id')
                ->where('installments_count', '<=', 1);
        })->orWhereNotNull('clone_of_id');
    }
}
