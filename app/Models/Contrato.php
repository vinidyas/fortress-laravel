<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContratoFormaPagamento;
use App\Enums\ContratoGarantiaTipo;
use App\Enums\ContratoReajusteIndice;
use App\Enums\ContratoStatus;
use App\Enums\ContratoTipo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos';

    protected $fillable = [
        'codigo_contrato',
        'imovel_id',
        'locador_id',
        'locatario_id',
        'data_inicio',
        'data_fim',
        'dia_vencimento',
        'prazo_meses',
        'carencia_meses',
        'data_entrega_chaves',
        'valor_aluguel',
        'desconto_mensal',
        'reajuste_indice',
        'reajuste_indice_outro',
        'reajuste_periodicidade_meses',
        'reajuste_teto_percentual',
        'data_proximo_reajuste',
        'garantia_tipo',
        'caucao_valor',
        'taxa_adm_percentual',
        'multa_atraso_percentual',
        'juros_mora_percentual_mes',
        'multa_rescisao_alugueis',
        'repasse_automatico',
        'conta_cobranca_id',
        'forma_pagamento_preferida',
        'tipo_contrato',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_proximo_reajuste' => 'date',
        'data_entrega_chaves' => 'date',
        'prazo_meses' => 'integer',
        'carencia_meses' => 'integer',
        'dia_vencimento' => 'integer',
        'reajuste_periodicidade_meses' => 'integer',
        'reajuste_teto_percentual' => 'decimal:2',
        'valor_aluguel' => 'decimal:2',
        'desconto_mensal' => 'decimal:2',
        'caucao_valor' => 'decimal:2',
        'taxa_adm_percentual' => 'decimal:2',
        'multa_atraso_percentual' => 'decimal:2',
        'juros_mora_percentual_mes' => 'decimal:2',
        'multa_rescisao_alugueis' => 'decimal:2',
        'repasse_automatico' => 'boolean',
        'reajuste_indice' => ContratoReajusteIndice::class,
        'garantia_tipo' => ContratoGarantiaTipo::class,
        'status' => ContratoStatus::class,
        'forma_pagamento_preferida' => ContratoFormaPagamento::class,
        'tipo_contrato' => ContratoTipo::class,
    ];

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    public function locador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'locador_id');
    }

    public function locatario(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'locatario_id');
    }

    public function fiadores(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'contrato_fiadores')->withTimestamps();
    }

    public function contaCobranca(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'conta_cobranca_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ContratoAnexo::class);
    }

    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }
}
