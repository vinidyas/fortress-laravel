<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaLancamento extends Model
{
    use HasFactory;

    protected $table = 'fatura_lancamentos';

    protected $fillable = [
        'fatura_id',
        'categoria',
        'descricao',
        'quantidade',
        'valor_unitario',
        'valor_total',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $qty = (float) ($item->quantidade ?? 0);
            $unit = (float) ($item->valor_unitario ?? 0);
            $item->valor_total = $qty * $unit;
        });
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }
}
