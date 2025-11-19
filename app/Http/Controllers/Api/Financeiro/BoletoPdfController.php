<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\FaturaBoleto;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Boleto\BoletoPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BoletoPdfController extends Controller
{
    public function __construct(
        private readonly BoletoPdfService $pdfService,
        private readonly BradescoBoletoGateway $gateway
    ) {}

    public function show(FaturaBoleto $boleto): Response
    {
        if (! $boleto->relationLoaded('fatura')) {
            $boleto->load('fatura');
        }

        $this->authorize('view', $boleto->fatura);

        $boleto = $this->ensureBoletoData($boleto);

        $pdf = $this->pdfService->generate($boleto);

        try {
            $this->pdfService->storeAsAttachment($boleto);
        } catch (\Throwable $exception) {
            Log::warning('[BoletoPdf] Falha ao anexar PDF após geração', [
                'boleto_id' => $boleto->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        $filename = sprintf(
            'boleto-%s.pdf',
            $boleto->nosso_numero ?: $boleto->id
        );

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function ensureBoletoData(FaturaBoleto $boleto): FaturaBoleto
    {
        $needsRefresh = ! $boleto->linha_digitavel
            || ! $boleto->codigo_barras
            || empty($boleto->response_payload);

        if (! $needsRefresh || ! $boleto->external_id) {
            return $boleto;
        }

        try {
            return $this->gateway->refreshStatus($boleto)->fresh(['fatura']);
        } catch (\Throwable $exception) {
            Log::warning('[Bradesco] Falha ao consultar boleto antes de gerar PDF', [
                'boleto_id' => $boleto->id,
                'exception' => $exception->getMessage(),
            ]);

            return $boleto;
        }
    }
}
