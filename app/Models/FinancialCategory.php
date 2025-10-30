<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'codigo',
        'nome',
        'tipo',
        'is_investment',
        'is_renovation',
        'ativo',
    ];

    protected $casts = [
        'is_investment' => 'boolean',
        'is_renovation' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('codigo');
    }
}
