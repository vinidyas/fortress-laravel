<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fatura extends Model
{
    use HasFactory;

    protected $table = 'faturas';

    protected $fillable = [
        'contrato_id',
        'competencia',
        'vencimento',
        'status',
        'valor_total',
        'valor_pago',
        'pago_em',
        'metodo_pagamento',
        'nosso_numero',
        'boleto_url',
        'pix_qrcode',
        'observacoes',
    ];

    protected $casts = [
        'competencia' => 'date',
        'vencimento' => 'date',
        'pago_em' => 'date',
        'valor_total' => 'decimal:2',
        'valor_pago' => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(FaturaLancamento::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(FaturaAnexo::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(FaturaEmailLog::class)->latest();
    }

    public function recalcTotals(): self
    {
        $total = $this->relationLoaded('itens')
            ? $this->itens->sum(fn (FaturaLancamento $item) => (float) $item->valor_total)
            : $this->itens()->sum('valor_total');

        $this->valor_total = $total;

        return $this;
    }
}
