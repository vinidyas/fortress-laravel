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
    ];

    protected $casts = [
        'parent_id' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)");
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'cost_center_id');
    }
}