<?php
namespace App\Jobs\Bradesco;

use App\Events\Boleto\BoletoCanceled;
use App\Events\Boleto\BoletoPaid;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBradescoWebhookPayload implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload)
    {
        $this->onQueue('boletos');
    }

    /**
     * Execute the job.
     */
    public function handle(BradescoBoletoGateway $gateway): void
    {
        $boleto = $this->resolveBoletoFromPayload();

        if (! $boleto) {
            return;
        }

        DB::transaction(function () use ($gateway, $boleto) {
            $lockedBoleto = FaturaBoleto::query()
                ->whereKey($boleto->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBoleto) {
                Log::warning('[Bradesco] Boleto não encontrado durante atualização transacional', [
                    'fatura_boleto_id' => $boleto->id,
                    'payload' => $this->payload,
                ]);

                return;
            }

            $lockedBoleto->webhook_payload = $this->payload;
            $lockedBoleto->save();

            $synced = $gateway->refreshStatus($lockedBoleto);
            $synced->loadMissing(['fatura.financialTransactions']);

            $this->syncFaturaFromBoleto($synced);
        });
    }

    /**
     * Exposto para testes.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    private function resolveBoletoFromPayload(): ?FaturaBoleto
    {
        $externalId = (string) Arr::get($this->payload, 'externalId', '');
        $nossoNumero = (string) Arr::get($this->payload, 'nossoNumero', '');

        $boleto = FaturaBoleto::query()
            ->when($externalId || $nossoNumero, function ($query) use ($externalId, $nossoNumero) {
                $query->where(function ($inner) use ($externalId, $nossoNumero) {
                    if ($externalId) {
                        $inner->where('external_id', $externalId);
                    }

                    if ($nossoNumero) {
                        $inner->orWhere('nosso_numero', $nossoNumero);
                    }
                });
            })
            ->first();

        if (! $boleto) {
            Log::warning('[Bradesco] Webhook recebido para boleto não encontrado', [
                'external_id' => $externalId,
                'nosso_numero' => $nossoNumero,
                'payload' => $this->payload,
            ]);

            return null;
        }

        return $boleto;
    }

    private function syncFaturaFromBoleto(FaturaBoleto $boleto): void
    {
        $fatura = $boleto->fatura;

        if (! $fatura) {
            Log::warning('[Bradesco] Boleto não possui fatura associada para atualização', [
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

        Log::info('[Bradesco] Fatura atualizada como paga via webhook', [
            'fatura_id' => $fatura->id,
            'fatura_boleto_id' => $boleto->id,
            'valor_pago' => $valorPago,
            'pago_em' => $dataPagamento,
        ]);

        event(new BoletoPaid($fatura->refresh(), $boleto->refresh()));
    }

    private function markFaturaAsCanceled(Fatura $fatura, FaturaBoleto $boleto): void
    {
        if ($fatura->status === 'Paga') {
            Log::info('[Bradesco] Webhook informou cancelamento, mas fatura segue paga. Nenhuma ação realizada.', [
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

        $this->updateFinancialTransactionsFromBoleto($fatura, $boleto, 'cancelado', optional($boleto->last_synced_at)->toDateString());

        Log::info('[Bradesco] Fatura cancelada devido a webhook de boleto', [
            'fatura_id' => $fatura->id,
            'fatura_boleto_id' => $boleto->id,
        ]);

        event(new BoletoCanceled($fatura->refresh(), $boleto->refresh()));
    }

    private function updateFinancialTransactionsFromBoleto(
        Fatura $fatura,
        FaturaBoleto $boleto,
        string $status,
        ?string $dataOcorrencia = null
    ): void {
        $fatura->loadMissing('financialTransactions');

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
