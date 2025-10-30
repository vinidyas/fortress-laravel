<?php

namespace App\Jobs\Bradesco;

use App\Models\FaturaBoleto;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncPendingBradescoBoletos implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $lote = 50)
    {
        $this->onQueue('boletos');
    }

    /**
     * Execute the job.
     */
    public function handle(BradescoBoletoGateway $gateway): void
    {
        FaturaBoleto::query()
            ->where('bank_code', BradescoApiClient::BANK_CODE)
            ->whereIn('status', [
                FaturaBoleto::STATUS_PENDING,
                FaturaBoleto::STATUS_REGISTERED,
            ])
            ->orderByDesc('vencimento')
            ->chunkById($this->lote, function ($boletos) use ($gateway) {
                $boletos->each(function (FaturaBoleto $boleto) use ($gateway) {
                    try {
                        $gateway->refreshStatus($boleto);
                    } catch (\Throwable $exception) {
                        Log::error('[Bradesco] Falha ao sincronizar boleto', [
                            'fatura_boleto_id' => $boleto->id,
                            'external_id' => $boleto->external_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                });
            });
    }
}
