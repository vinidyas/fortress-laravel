<?php

declare(strict_types=1);

namespace App\Services\Boleto;

use App\Events\Boleto\BoletoCanceled;
use App\Events\Boleto\BoletoPaid;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use Illuminate\Support\Facades\Log;

class BoletoFaturaSyncService
{
    public function sync(FaturaBoleto $boleto): void
    {
        $boleto->loadMissing('fatura.financialTransactions');

        $fatura = $boleto->fatura;

        if (! $fatura) {
            Log::warning('[Bradesco] Boleto não possui fatura associada para sincronização da fatura', [
                'fatura_boleto_id' => $boleto->id,
                'external_id' => $boleto->external_id,
                'status' => $boleto->status,
            ]);

            return;
        }

        match ($boleto->status) {
            FaturaBoleto::STATUS_PAID => $this->markFaturaAsPaid($fatura, $boleto),
            FaturaBoleto::STATUS_CANCELED => $this->markFaturaAsCanceled($fatura, $boleto),
            default => null,
        };
    }

    private function markFaturaAsPaid(Fatura $fatura, FaturaBoleto $boleto): void
    {
        $valorPago = (float) ($boleto->valor_pago ?? $boleto->valor ?? 0);
        $dataPagamento = optional($boleto->liquidado_em)->toDateString() ?? now()->toDateString();

        $fatura->fill([
            'status' => 'Paga',
            'metodo_pagamento' => 'Boleto',
            'valor_pago' => $valorPago,
            'pago_em' => $dataPagamento,
            'nosso_numero' => $boleto->nosso_numero ?? $fatura->nosso_numero,
            'boleto_url' => $boleto->pdf_url ?? $fatura->boleto_url,
        ]);

        $fatura->save();

        $this->updateFinancialTransactionsFromBoleto($fatura, $boleto, 'conciliado', $dataPagamento);

        event(new BoletoPaid($fatura->refresh(), $boleto->refresh()));
    }

    private function markFaturaAsCanceled(Fatura $fatura, FaturaBoleto $boleto): void
    {
        if ($fatura->status === 'Paga') {
            Log::info('[Bradesco] Boleto cancelado, mas fatura permanece paga. Nenhuma alteração realizada.', [
                'fatura_id' => $fatura->id,
                'fatura_boleto_id' => $boleto->id,
            ]);

            return;
        }

        $fatura->fill([
            'status' => 'Cancelada',
            'metodo_pagamento' => $fatura->metodo_pagamento ?? 'Boleto',
        ]);

        $fatura->save();

        $this->updateFinancialTransactionsFromBoleto(
            $fatura,
            $boleto,
            'cancelado',
            optional($boleto->last_synced_at)->toDateString()
        );

        event(new BoletoCanceled($fatura->refresh(), $boleto->refresh()));
    }

    private function updateFinancialTransactionsFromBoleto(
        Fatura $fatura,
        FaturaBoleto $boleto,
        string $status,
        ?string $dataOcorrencia = null
    ): void {
        if ($fatura->financialTransactions->isEmpty()) {
            return;
        }

        foreach ($fatura->financialTransactions as $transaction) {
            $transaction->status = $status;

            if ($dataOcorrencia) {
                $transaction->data_ocorrencia = $dataOcorrencia;
            }

            $meta = $transaction->meta ?? [];
            $meta['boleto_sync'] = [
                'fatura_boleto_id' => $boleto->id,
                'status' => $boleto->status,
                'last_synced_at' => optional($boleto->last_synced_at)->toIso8601String() ?? now()->toIso8601String(),
            ];

            $transaction->meta = $meta;
            $transaction->save();
        }
    }
}
