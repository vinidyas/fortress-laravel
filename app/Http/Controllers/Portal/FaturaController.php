<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Fatura;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class FaturaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $pessoaId = $user?->pessoa_id;
        $contratoId = $request->integer('contrato_id');

        $faturas = Fatura::query()
            ->with([
                'boletos' => fn ($query) => $query->latest('created_at')->limit(1),
            ])
            ->whereHas('contrato', fn ($query) => $query->where('locatario_id', $pessoaId))
            ->when($contratoId, fn ($query) => $query->where('contrato_id', $contratoId))
            ->orderByDesc('competencia')
            ->limit(100)
            ->get()
            ->map(function (Fatura $fatura) {
                $boleto = $fatura->boletos->first();

                return [
                    'id' => $fatura->id,
                    'contrato_id' => $fatura->contrato_id,
                    'competencia' => optional($fatura->competencia)->toDateString(),
                    'vencimento' => optional($fatura->vencimento)->toDateString(),
                    'status' => $fatura->status,
                    'valor_total' => (float) $fatura->valor_total,
                    'valor_pago' => (float) ($fatura->valor_pago ?? 0),
                    'pago_em' => optional($fatura->pago_em)->toDateString(),
                    'receipt_url' => $this->resolveReceiptUrl($fatura),
                    'boleto' => $boleto ? [
                        'id' => $boleto->id,
                        'status' => $boleto->status,
                        'linha_digitavel' => $boleto->linha_digitavel,
                        'codigo_barras' => $boleto->codigo_barras,
                        'pdf_url' => $boleto->pdf_url,
                        'nosso_numero' => $boleto->nosso_numero,
                        'valor' => (float) $boleto->valor,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $faturas,
        ]);
    }

    public function show(Request $request, int $fatura): JsonResponse
    {
        $faturaModel = $this->findFaturaForTenant($request, $fatura);

        $faturaModel->load([
            'boletos' => fn ($query) => $query->orderByDesc('created_at'),
            'itens' => fn ($query) => $query->orderBy('created_at'),
        ]);

        return response()->json([
            'data' => [
                'id' => $faturaModel->id,
                'contrato_id' => $faturaModel->contrato_id,
                'competencia' => optional($faturaModel->competencia)->toDateString(),
                'vencimento' => optional($faturaModel->vencimento)->toDateString(),
                'status' => $faturaModel->status,
                'valor_total' => (float) $faturaModel->valor_total,
                'valor_pago' => (float) ($faturaModel->valor_pago ?? 0),
                'pago_em' => optional($faturaModel->pago_em)->toDateString(),
                'metodo_pagamento' => $faturaModel->metodo_pagamento,
                'receipt_url' => $this->resolveReceiptUrl($faturaModel),
                'itens' => $faturaModel->itens->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'descricao' => $item->descricao,
                        'categoria' => $item->categoria,
                        'valor' => (float) $item->valor_total,
                    ];
                }),
                'boletos' => $faturaModel->boletos->map(function ($boleto) {
                    return [
                        'id' => $boleto->id,
                        'status' => $boleto->status,
                        'status_label' => $boleto->statusLabel(),
                        'valor' => (float) $boleto->valor,
                        'valor_pago' => (float) ($boleto->valor_pago ?? 0),
                        'vencimento' => optional($boleto->vencimento)->toDateString(),
                        'registrado_em' => optional($boleto->registrado_em)->toDateTimeString(),
                        'liquidado_em' => optional($boleto->liquidado_em)->toDateTimeString(),
                        'linha_digitavel' => $boleto->linha_digitavel,
                        'codigo_barras' => $boleto->codigo_barras,
                        'pdf_url' => $boleto->pdf_url,
                        'nosso_numero' => $boleto->nosso_numero,
                    ];
                }),
            ],
        ]);
    }

    public function receipt(Request $request, int $fatura, ViewFactory $viewFactory): View
    {
        $faturaModel = $this->findFaturaForTenant($request, $fatura);

        $faturaModel->load(['contrato.locatario', 'contrato.locador', 'contrato.imovel', 'itens']);

        $logoPath = base_path('docs/identidade-visual-fortress_3.jpg');
        $logoBase64 = null;

        if (File::exists($logoPath)) {
            $logoBase64 = 'data:image/jpeg;base64,'.base64_encode(File::get($logoPath));
        }

        return $viewFactory->make('receipts.fatura', [
            'fatura' => $faturaModel,
            'logoBase64' => $logoBase64,
            'company' => [
                'name' => 'Fortress Empreendimentos',
                'phone' => 'Tel: (11) 97279-4688 ou (11) 99388-2274',
                'email' => 'contato@fortressempreendimentos.com.br ; atendimento@fortressempreendimentos.com.br',
            ],
        ]);
    }

    private function findFaturaForTenant(Request $request, int $faturaId): Fatura
    {
        $user = $request->user();
        $pessoaId = $user?->pessoa_id;

        $fatura = Fatura::query()
            ->whereKey($faturaId)
            ->whereHas('contrato', fn ($query) => $query->where('locatario_id', $pessoaId))
            ->first();

        if (! $fatura) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $fatura;
    }

    private function resolveReceiptUrl(Fatura $fatura): ?string
    {
        if (strtolower((string) $fatura->status) !== 'paga') {
            return null;
        }

        return route('portal.invoices.receipt', ['fatura' => $fatura->id], false);
    }
}
