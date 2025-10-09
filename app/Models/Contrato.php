<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos';

    protected $fillable = [
        'codigo_contrato',
        'imovel_id',
        'locador_id',
        'locatario_id',
        'fiador_id',
        'data_inicio',
        'data_fim',
        'dia_vencimento',
        'valor_aluguel',
        'reajuste_indice',
        'data_proximo_reajuste',
        'garantia_tipo',
        'caucao_valor',
        'taxa_adm_percentual',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_proximo_reajuste' => 'date',
        'valor_aluguel' => 'decimal:2',
        'caucao_valor' => 'decimal:2',
        'taxa_adm_percentual' => 'decimal:2',
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

    public function fiador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'fiador_id');
    }
}