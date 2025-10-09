<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFinanceiroFilterRequest;
use App\Models\Fatura;
use App\Models\FinancialTransaction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportFinanceiroController extends Controller
{
    public function index(ReportFinanceiroFilterRequest $request): JsonResponse
    {
        $transactions = $this->filteredTransactions($request);

        $receitas = (clone $transactions)->where('tipo', 'credito')->sum('valor');
        $despesas = (clone $transactions)->where('tipo', 'debito')->sum('valor');
        $saldo = $receitas - $despesas;

        $inadimplencia = Fatura::query()
            ->where('status', 'Aberta')
            ->when($request->filled('de'), fn ($q) => $q->whereDate('vencimento', '>=', $request->date('de')->toDateString()))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('vencimento', '<=', $request->date('ate')->toDateString()))
            ->with(['contrato.imovel'])
            ->get()
            ->map(fn (Fatura $fatura) => [
                'id' => $fatura->id,
                'contrato' => optional($fatura->contrato)->codigo_contrato,
                'imovel' => optional(optional($fatura->contrato)->imovel)->codigo,
                'vencimento' => optional($fatura->vencimento)->toDateString(),
                'valor_total' => (float) $fatura->valor_total,
            ]);

        return response()->json([
            'totals' => [
                'receitas' => $receitas,
                'despesas' => $despesas,
                'saldo' => $saldo,
            ],
            'inadimplencia' => $inadimplencia,
        ]);
    }

    public function export(ReportFinanceiroFilterRequest $request): StreamedResponse
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $format = strtolower((string) $request->input('format', 'csv'));
        if ($format !== 'csv') {
            abort(422, 'Formato solicitado nao e suportado.');
        }

        $transactions = $this->filteredTransactions($request)->orderBy('data_ocorrencia')->get();
        $filename = 'relatorio-financeiro-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Data', 'Tipo', 'Valor', 'Conta', 'Status', 'Descricao']);

            foreach ($transactions as $row) {
                fputcsv($handle, [
                    optional($row->data_ocorrencia)->toDateString(),
                    $row->tipo,
                    number_format((float) $row->valor, 2, '.', ''),
                    optional($row->account)->nome,
                    $row->status,
                    $row->descricao,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredTransactions(ReportFinanceiroFilterRequest $request)
    {
        return FinancialTransaction::query()
            ->with('account')
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('de'), fn ($q) => $q->whereDate('data_ocorrencia', '>=', $request->date('de')->toDateString()))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('data_ocorrencia', '<=', $request->date('ate')->toDateString()));
    }
}
