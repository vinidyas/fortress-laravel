<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'descricao',
        'codigo',
        'parent_id',
        'tipo',
        'ativo',
        'orcamento_anual',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'ativo' => 'boolean',
        'orcamento_anual' => 'decimal:2',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)");
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'cost_center_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'cost_center_id');
    }
}
