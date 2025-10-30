<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAccount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'nome',
        'apelido',
        'tipo',
        'instituicao',
        'banco',
        'agencia',
        'numero',
        'carteira',
        'moeda',
        'saldo_inicial',
        'data_saldo_inicial',
        'saldo_atual',
        'limite_credito',
        'categoria',
        'permite_transf',
        'padrao_recebimento',
        'padrao_pagamento',
        'integra_config',
        'observacoes',
        'ativo',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'data_saldo_inicial' => 'date',
        'saldo_atual' => 'decimal:2',
        'limite_credito' => 'decimal:2',
        'permite_transf' => 'boolean',
        'padrao_recebimento' => 'boolean',
        'padrao_pagamento' => 'boolean',
        'integra_config' => 'array',
        'ativo' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'account_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'bank_account_id');
    }

    public function counterJournalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'counter_bank_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'financial_account_id');
    }

    public function bankStatements(): HasMany
    {
        return $this->hasMany(BankStatement::class, 'financial_account_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            if ($account->saldo_atual === null) {
                $account->saldo_atual = $account->saldo_inicial;
            }

            if (! $account->moeda) {
                $account->moeda = 'BRL';
            }

            if (! $account->categoria) {
                $account->categoria = 'operacional';
            }
        });
    }
}
