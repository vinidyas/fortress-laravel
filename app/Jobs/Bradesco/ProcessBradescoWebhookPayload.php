<?php

namespace App\Jobs\Bradesco;

use App\Models\FaturaBoleto;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Banking\Bradesco\Support\BradescoPayloadSanitizer;
use App\Services\Boleto\BoletoFaturaSyncService;
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

        if ($this->usingSandboxFixtures()) {
            $this->applySandboxWebhook($boleto);

            return;
        }

        $syncService = app(BoletoFaturaSyncService::class);

        DB::transaction(function () use ($gateway, $boleto, $syncService) {
            $lockedBoleto = FaturaBoleto::query()
                ->whereKey($boleto->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBoleto) {
                Log::warning('[Bradesco] Boleto não encontrado durante atualização transacional', [
                    'fatura_boleto_id' => $boleto->id,
                    'payload' => $this->sanitizedPayload(),
                ]);

                return;
            }

            $lockedBoleto->webhook_payload = $this->sanitizedPayload();
            $lockedBoleto->save();

            $synced = $gateway->refreshStatus($lockedBoleto);
            $synced->loadMissing(['fatura.financialTransactions']);

            $syncService->sync($synced);
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
            ->where('bank_code', BradescoApiClient::BANK_CODE)
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
            ->latest('id')
            ->first();

        if (! $boleto) {
            Log::warning('[Bradesco] Webhook recebido para boleto não encontrado', [
                'external_id' => $externalId,
                'nosso_numero' => $nossoNumero,
                'payload' => $this->sanitizedPayload(),
            ]);

            return null;
        }

        return $boleto;
    }

    private function applySandboxWebhook(FaturaBoleto $boleto): void
    {
        $syncService = app(BoletoFaturaSyncService::class);

        DB::transaction(function () use ($boleto, $syncService) {
            $locked = FaturaBoleto::query()
                ->whereKey($boleto->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return;
            }

            $status = $this->resolveStatusFromPayload();
            $valorPago = Arr::get($this->payload, 'valorPago');
            $dataEvento = Arr::get($this->payload, 'dataPagamento') ?: Arr::get($this->payload, 'dataEvento');

            $locked->fill([
                'status' => $status ?? $locked->status,
                'valor_pago' => $valorPago !== null ? (float) $valorPago : $locked->valor_pago,
                'liquidado_em' => $dataEvento ? now()->parse($dataEvento) : $locked->liquidado_em,
                'last_synced_at' => now(),
            ]);

            if ($status === FaturaBoleto::STATUS_PAID && ! $locked->pdf_url) {
                $locked->pdf_url = config('services.bradesco_boleto.sandbox_pdf_url');
            }

            $locked->webhook_payload = $this->sanitizedPayload();
            $locked->save();

            $syncService->sync($locked);
        });
    }

    private function usingSandboxFixtures(): bool
    {
        return config('services.bradesco_boleto.sandbox_use_fixtures', false) === true;
    }

    private function resolveStatusFromPayload(): ?string
    {
        $event = strtolower((string) Arr::get($this->payload, 'event', ''));

        return match (true) {
            str_contains($event, 'liquid') => FaturaBoleto::STATUS_PAID,
            str_contains($event, 'cancel') => FaturaBoleto::STATUS_CANCELED,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizedPayload(): array
    {
        return BradescoPayloadSanitizer::sanitize($this->payload);
    }

}
