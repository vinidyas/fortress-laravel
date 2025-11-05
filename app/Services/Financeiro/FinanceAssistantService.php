<?php

namespace App\Services\Financeiro;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FinanceAssistantService
{
    public function respond(string $message): array
    {
        $snapshot = $this->collectSnapshot();
        $sanitized = $this->sanitizeSnapshot($snapshot);

        $reply = $this->callOpenAi($message, $sanitized);

        if ($reply === null) {
            $reply = $this->fallbackReply($message, $snapshot);
        }

        return [
            'reply' => $reply,
            'context' => $snapshot['context'],
        ];
    }

    private function collectSnapshot(): array
    {
        $today = Carbon::today();

        $openInvoicesQuery = Fatura::query()->where('status', 'Aberta');
        $openInvoicesCount = (clone $openInvoicesQuery)->count();
        $openInvoicesTotal = (float) (clone $openInvoicesQuery)->sum('valor_total');

        $overdueInvoicesQuery = (clone $openInvoicesQuery)
            ->with(['contrato:id,codigo_contrato'])
            ->whereDate('vencimento', '<', $today)
            ->orderBy('vencimento');
        $overdueInvoicesCount = (clone $overdueInvoicesQuery)->count();
        $overdueInvoicesList = $overdueInvoicesQuery
            ->limit(5)
            ->get()
            ->map(function (Fatura $fatura) use ($today) {
                $dueDate = $fatura->vencimento ? Carbon::parse($fatura->vencimento) : null;

                return [
                    'id' => $fatura->id,
                    'reference' => $fatura->competencia?->format('m/Y'),
                    'due_date' => $dueDate?->toDateString(),
                    'status' => $fatura->status,
                    'amount' => (float) $fatura->valor_total,
                    'contract' => $fatura->contrato?->codigo_contrato,
                    'days_overdue' => $dueDate ? $dueDate->diffInDays($today) : null,
                ];
            })
            ->all();

        $contractsActive = Contrato::query()->where('status', 'Ativo')->count();

        $payablesBase = JournalEntry::query()
            ->with(['costCenter:id,nome'])
            ->where('type', 'despesa')
            ->whereIn('status', ['pendente', 'atrasado']);

        $payablesTodayCount = (clone $payablesBase)
            ->whereDate('due_date', $today)
            ->count();
        $payablesTodayList = (clone $payablesBase)
            ->whereDate('due_date', $today)
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(function (JournalEntry $entry) {
                return [
                    'id' => $entry->id,
                    'description' => $entry->description_custom ?? $entry->notes ?? ('Lançamento '.$entry->id),
                    'amount' => (float) $entry->amount,
                    'due_date' => optional($entry->due_date)?->toDateString(),
                    'status' => $entry->status,
                    'cost_center' => $entry->costCenter?->nome,
                ];
            })
            ->all();

        $payablesOverdueQuery = (clone $payablesBase)
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date');
        $payablesOverdueCount = (clone $payablesOverdueQuery)->count();
        $payablesOverdueList = $payablesOverdueQuery
            ->limit(5)
            ->get()
            ->map(function (JournalEntry $entry) use ($today) {
                $dueDate = $entry->due_date ? Carbon::parse($entry->due_date) : null;

                return [
                    'id' => $entry->id,
                    'description' => $entry->description_custom ?? $entry->notes ?? ('Lançamento '.$entry->id),
                    'amount' => (float) $entry->amount,
                    'due_date' => $dueDate?->toDateString(),
                    'status' => $entry->status,
                    'cost_center' => $entry->costCenter?->nome,
                    'days_overdue' => $dueDate ? $dueDate->diffInDays($today) : null,
                ];
            })
            ->all();

        return [
            'today' => $today,
            'context' => [
                'contracts_active' => $contractsActive,
                'invoices_open' => $openInvoicesCount,
                'invoices_open_total' => $openInvoicesTotal,
                'invoices_overdue' => $overdueInvoicesCount,
                'payables_today' => $payablesTodayCount,
                'payables_overdue' => $payablesOverdueCount,
                'overdue_invoices' => $overdueInvoicesList,
                'payables_today_list' => $payablesTodayList,
                'payables_overdue_list' => $payablesOverdueList,
            ],
        ];
    }

    private function sanitizeSnapshot(array $snapshot): array
    {
        $context = $snapshot['context'];

        return [
            'metrics' => [
                'contracts_active' => $context['contracts_active'],
                'invoices_open' => $context['invoices_open'],
                'invoices_open_total' => $context['invoices_open_total'],
                'invoices_overdue' => $context['invoices_overdue'],
                'payables_today' => $context['payables_today'],
                'payables_overdue' => $context['payables_overdue'],
            ],
            'overdue_invoices' => array_map(fn ($invoice) => [
                'amount' => $invoice['amount'],
                'due_date' => $invoice['due_date'],
                'days_overdue' => $invoice['days_overdue'],
            ], $context['overdue_invoices']),
            'payables_today' => array_map(fn ($item) => [
                'amount' => $item['amount'],
                'due_date' => $item['due_date'],
                'status' => $item['status'],
            ], $context['payables_today_list']),
            'payables_overdue' => array_map(fn ($item) => [
                'amount' => $item['amount'],
                'due_date' => $item['due_date'],
                'days_overdue' => $item['days_overdue'],
                'status' => $item['status'],
            ], $context['payables_overdue_list']),
        ];
    }

    private function callOpenAi(string $message, array $snapshot): ?string
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            return null;
        }

        $baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');
        $model = config('services.openai.model', 'gpt-4o-mini');
        $timeout = (float) config('services.openai.timeout', 10.0);

        $systemPrompt = <<<'PROMPT'
Você é um assistente financeiro para um sistema de gestão imobiliária. Responda sempre em português Brasil, de forma direta, organizada e com foco em números e próximos passos. Nunca invente dados: utilize apenas o resumo fornecido. Quando houver atrasos ou riscos, destaque-os claramente.
PROMPT;

        $userContent = implode("\n\n", [
            'Mensagem do usuário: '.$message,
            'Resumo financeiro (dados mascarados):',
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($timeout)
                ->post($baseUrl, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                    'temperature' => 0.2,
                ]);

            if ($response->failed()) {
                return null;
            }

            $reply = trim((string) Arr::get($response->json(), 'choices.0.message.content'));

            return $reply !== '' ? $reply : null;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function fallbackReply(string $message, array $snapshot): string
    {
        $context = $snapshot['context'];
        $normalized = Str::lower(Str::squish($message));
        $currency = fn (float $value): string => number_format($value, 2, ',', '.');

        $oldestInvoice = $context['overdue_invoices'][0] ?? null;
        $oldestPayable = $context['payables_overdue_list'][0] ?? null;

        return match (true) {
            Str::contains($normalized, ['resumo', 'dashboard', 'visão']) => sprintf(
                'Resumo do dia: %d contratos ativos. %d faturas abertas somando R$ %s, com %d em atraso. Há %d contas vencendo hoje e %d pendentes de dias anteriores.',
                $context['contracts_active'],
                $context['invoices_open'],
                $currency($context['invoices_open_total']),
                $context['invoices_overdue'],
                $context['payables_today'],
                $context['payables_overdue']
            ),
            Str::contains($normalized, ['fatura', 'boleto', 'cobrança']) => $context['invoices_overdue'] > 0
                ? sprintf(
                    'Temos %d faturas abertas (R$ %s). %d estão atrasadas; a mais antiga vence em %s e está há %d dia(s) em atraso.',
                    $context['invoices_open'],
                    $currency($context['invoices_open_total']),
                    $context['invoices_overdue'],
                    $oldestInvoice['due_date'] ?? 'data não informada',
                    $oldestInvoice['days_overdue'] ?? 0
                )
                : sprintf(
                    'Temos %d faturas abertas totalizando R$ %s. Nenhuma está atrasada.',
                    $context['invoices_open'],
                    $currency($context['invoices_open_total'])
                ),
            Str::contains($normalized, ['conta', 'pagar', 'despesa']) => $context['payables_overdue'] > 0
                ? sprintf(
                    'Contas a pagar: %d vencem hoje e %d seguem atrasadas. A conta mais antiga está atrasada há %d dia(s).',
                    $context['payables_today'],
                    $context['payables_overdue'],
                    $oldestPayable['days_overdue'] ?? 0
                )
                : sprintf(
                    'Contas a pagar: %d vencem hoje e não há lançamentos atrasados.',
                    $context['payables_today']
                ),
            Str::contains($normalized, ['contrato', 'locação']) => sprintf(
                'Atualmente existem %d contratos ativos. Recomendo acompanhar reajustes e vencimentos próximos na tela de Contratos.',
                $context['contracts_active']
            ),
            default => sprintf(
                'Posso gerar um resumo financeiro rápido. Exemplos: "Resumo do dia", "Faturas em atraso", "Contas a pagar" ou "Contratos ativos".%sResumo atual: %d contratos ativos, %d faturas abertas (R$ %s) e %d contas vencendo hoje.',
                PHP_EOL,
                $context['contracts_active'],
                $context['invoices_open'],
                $currency($context['invoices_open_total']),
                $context['payables_today']
            ),
        };
    }
}
