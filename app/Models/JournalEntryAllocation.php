<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'cost_center_id',
        'property_id',
        'percentage',
        'amount',
    ];

    protected $casts = [
        'percentage' => 'decimal:3',
        'amount' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Imovel::class, 'property_id');
    }
}
