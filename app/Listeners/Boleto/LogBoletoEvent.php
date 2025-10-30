<?php

namespace App\Listeners\Boleto;

use App\Events\Boleto\BoletoCanceled;
use App\Events\Boleto\BoletoPaid;
use App\Events\Boleto\BoletoRegistered;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class LogBoletoEvent
{
    public function handle(object $event): void
    {
        $context = [
            'fatura_id' => $event->fatura->id ?? null,
            'contrato_id' => $event->fatura->contrato_id ?? null,
            'fatura_status' => $event->fatura->status ?? null,
            'boleto_id' => $event->boleto->id ?? null,
            'boleto_status' => $event->boleto->status ?? null,
            'valor' => $event->boleto->valor,
            'vencimento' => optional($event->boleto->vencimento)->toDateString(),
            'external_id' => $event->boleto->external_id,
            'nosso_numero' => $event->boleto->nosso_numero,
            'linha_digitavel_masked' => $this->maskLinhaDigitavel($event->boleto->linha_digitavel),
        ];

        $message = match (true) {
            $event instanceof BoletoPaid => '[Bradesco] Boleto marcado como pago',
            $event instanceof BoletoCanceled => '[Bradesco] Boleto cancelado',
            $event instanceof BoletoRegistered => '[Bradesco] Boleto registrado',
            default => '[Bradesco] Evento de boleto registrado',
        };

        Log::channel('bradesco')->info($message, Arr::where($context, fn ($value) => $value !== null));
    }

    private function maskLinhaDigitavel(?string $linha): ?string
    {
        if (! $linha) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $linha) ?: '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 8) {
            return str_repeat('*', max(strlen($digits) - 2, 0)) . substr($digits, -2);
        }

        $first = substr($digits, 0, 5);
        $last = substr($digits, -4);

        return $first . str_repeat('*', strlen($digits) - 9) . $last;
    }
}
