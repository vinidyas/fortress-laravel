<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFinanceiroFilterRequest;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportFinanceiroController extends Controller
{
    public function index(ReportFinanceiroFilterRequest $request): JsonResponse
    {
        $entries = $this->filteredEntries($request);

        $openStatuses = JournalEntryStatus::filterValues('open');
        $settledStatuses = JournalEntryStatus::filterValues('settled');
        $cancelledStatuses = JournalEntryStatus::filterValues('cancelled');

        $paidEntries = (clone $entries)->whereIn('status', $settledStatuses);
        $receitas = (clone $paidEntries)->where('type', 'receita')->sum('amount');
        $despesas = (clone $paidEntries)->where('type', 'despesa')->sum('amount');
        $saldo = $receitas - $despesas;

        $emAberto = (clone $entries)->whereIn('status', $openStatuses)->sum('amount');
        $quitado = (clone $entries)->whereIn('status', $settledStatuses)->sum('amount');
        $cancelado = (clone $entries)->whereIn('status', $cancelledStatuses)->sum('amount');

        return response()->json([
            'totals' => [
                'receitas' => (float) $receitas,
                'despesas' => (float) $despesas,
                'saldo' => (float) $saldo,
                'em_aberto' => (float) $emAberto,
                'quitado' => (float) $quitado,
                'cancelado' => (float) $cancelado,
            ],
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

        $entries = $this->filteredEntries($request)->orderBy('movement_date')->get();
        $filename = 'relatorio-financeiro-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($entries) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Data', 'Tipo', 'Valor', 'Conta', 'Status', 'Descricao']);

            foreach ($entries as $row) {
                $statusEnum = $row->status ? JournalEntryStatus::tryFrom((string) $row->status) : null;
                $typeEnum = $row->type ? JournalEntryType::tryFrom((string) $row->type) : null;
                $statusLabel = $statusEnum ? $statusEnum->label($typeEnum) : $row->status;

                fputcsv($handle, [
                    optional($row->movement_date)->toDateString(),
                    $row->type,
                    number_format((float) $row->amount, 2, '.', ''),
                    optional($row->bankAccount)->nome,
                    $statusLabel,
                    $row->description_custom,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredEntries(ReportFinanceiroFilterRequest $request)
    {
        return JournalEntry::query()
            ->with('bankAccount')
            ->when($request->filled('account_id'), fn ($q) => $q->where('bank_account_id', $request->integer('account_id')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $statuses = JournalEntryStatus::filterValues((string) $request->string('status'));

                return count($statuses) === 1
                    ? $q->where('status', $statuses[0])
                    : $q->whereIn('status', $statuses);
            })
            ->when($request->filled('de'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('de')->toDateString()))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('ate')->toDateString()));
    }
}
