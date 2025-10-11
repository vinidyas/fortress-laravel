<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'valor_total',
        'parcela_atual',
        'total_parcelas',
        'vencimento',
        'status',
        'meta',
    ];

    protected $attributes = [
        'status' => 'aberto',
    ];

    protected $casts = [
        'valor_total' => 'decimal:2',
        'vencimento' => 'date',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $schedule) {
            if ($schedule->status === 'cancelado') {
                return;
            }

            if ($schedule->vencimento instanceof Carbon && $schedule->status === 'aberto') {
                if ($schedule->vencimento->lt(Carbon::today())) {
                    $schedule->status = 'em_atraso';
                }
            }

            if (
                $schedule->total_parcelas !== null &&
                $schedule->parcela_atual !== null &&
                $schedule->parcela_atual >= $schedule->total_parcelas &&
                $schedule->status !== 'cancelado'
            ) {
                $schedule->status = 'quitado';
            }
        });
    }
}
