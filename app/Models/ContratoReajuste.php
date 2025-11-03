<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoReajuste extends Model
{
    use HasFactory;

    protected $table = 'contrato_reajustes';

    protected $fillable = [
        'contrato_id',
        'usuario_id',
        'indice',
        'percentual_aplicado',
        'valor_anterior',
        'valor_novo',
        'valor_reajuste',
        'teto_percentual',
        'data_base_reajuste',
        'data_proximo_reajuste_anterior',
        'data_proximo_reajuste_novo',
        'observacoes',
    ];

    protected $casts = [
        'percentual_aplicado' => 'decimal:4',
        'valor_anterior' => 'decimal:2',
        'valor_novo' => 'decimal:2',
        'valor_reajuste' => 'decimal:2',
        'teto_percentual' => 'decimal:4',
        'data_base_reajuste' => 'date',
        'data_proximo_reajuste_anterior' => 'date',
        'data_proximo_reajuste_novo' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
