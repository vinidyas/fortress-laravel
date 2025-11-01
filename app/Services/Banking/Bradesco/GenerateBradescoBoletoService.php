<?php

namespace App\Services\Banking\Bradesco;

use App\Events\Boleto\BoletoRegistered;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Services\Boleto\BoletoGateway;
use Illuminate\Support\Facades\Log;

class GenerateBradescoBoletoService
{
    /** @var array<string, mixed> */
    private array $defaultContext;

    /**
     * @param  array<string, mixed>  $defaultContext
     */
    public function __construct(
        private readonly BoletoGateway $gateway,
        array $defaultContext = []
    ) {
        $this->defaultContext = $defaultContext;
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    public function handle(Fatura $fatura, array $contexto = []): FaturaBoleto
    {
        $fatura->loadMissing(['contrato.locatario', 'itens']);

        $ultimoBoleto = $fatura->boletos()
            ->whereIn('status', [
                FaturaBoleto::STATUS_PENDING,
                FaturaBoleto::STATUS_REGISTERED,
            ])
            ->latest()
            ->first();

        if ($ultimoBoleto) {
            $this->syncFaturaWithBoleto($fatura->refresh(), $ultimoBoleto);

            return $ultimoBoleto;
        }

        $payloadContext = array_replace_recursive($this->defaultContext, $contexto);

        Log::info('[Bradesco] Iniciando emissão de boleto', [
            'fatura_id' => $fatura->id,
            'contrato_id' => $fatura->contrato_id,
        ]);

        $boleto = $this->gateway->issue($fatura, $payloadContext);
        $wasCreated = $boleto->wasRecentlyCreated;

        $this->syncFaturaWithBoleto($fatura->refresh(), $boleto);

        if ($wasCreated) {
            event(new BoletoRegistered($fatura->fresh(), $boleto->fresh()));
        }

        return $boleto;
    }

    protected function syncFaturaWithBoleto(Fatura $fatura, FaturaBoleto $boleto): void
    {
        $fatura->fill([
            'nosso_numero' => $boleto->nosso_numero ?? $fatura->nosso_numero,
            'boleto_url' => $boleto->pdf_url ?? $fatura->boleto_url,
            'metodo_pagamento' => $fatura->metodo_pagamento ?: 'Boleto',
        ]);

        if ($fatura->status === 'Cancelada') {
            $fatura->status = 'Aberta';
            $fatura->valor_pago = null;
            $fatura->pago_em = null;
        }

        $fatura->save();
    }
}
