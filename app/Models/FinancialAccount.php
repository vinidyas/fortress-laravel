<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'tipo',
        'banco',
        'agencia',
        'numero',
        'saldo_inicial',
        'ativo',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'account_id');
    }
}