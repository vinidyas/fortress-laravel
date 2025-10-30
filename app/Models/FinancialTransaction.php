<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'cost_center_id',
        'contrato_id',
        'fatura_id',
        'parent_transaction_id',
        'tipo',
        'valor',
        'data_ocorrencia',
        'descricao',
        'status',
        'meta',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_ocorrencia' => 'date',
        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }
}
