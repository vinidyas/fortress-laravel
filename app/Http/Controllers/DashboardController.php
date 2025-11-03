<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\JournalEntry;
use App\Models\Imovel;
use App\Models\DashboardAlert;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $metrics = [
            'propertiesTotal' => Imovel::count(),
            'propertiesAvailable' => Imovel::where('disponibilidade', 'Disponivel')->count(),
            'propertiesUnavailable' => Imovel::where('disponibilidade', 'Indisponivel')->count(),
            'activeContracts' => Contrato::where('status', 'Ativo')->count(),
            'contractsExpiringSoon' => Contrato::where('status', 'Ativo')
                ->whereNotNull('data_fim')
                ->whereBetween('data_fim', [$now->copy()->startOfDay(), $now->copy()->addDays(30)])
                ->count(),
            'openInvoices' => Fatura::where('status', 'Aberta')->count(),
            'overdueInvoices' => Fatura::where('status', 'Aberta')
                ->whereDate('vencimento', '<', $now->toDateString())
                ->count(),
            'openAmount' => (float) Fatura::where('status', 'Aberta')->sum('valor_total'),
            'paidThisMonth' => (float) Fatura::where('status', 'Paga')
                ->whereBetween('pago_em', [$startOfMonth, $endOfMonth])
                ->sum('valor_pago'),
        ];

        $financialTrend = $this->buildFinancialTrend($now);
        $delinquency = $this->buildDelinquencySummary($metrics['openAmount'], $metrics['paidThisMonth']);

        $expiringContracts = Contrato::query()
            ->with(['imovel'])
            ->where('status', 'Ativo')
            ->whereNotNull('data_fim')
            ->whereBetween('data_fim', [$now->copy()->startOfDay(), $now->copy()->addDays(30)])
            ->orderBy('data_fim')
            ->limit(5)
            ->get()
            ->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'code' => $contrato->codigo_contrato,
                'imovel' => $contrato->imovel?->codigo,
                'endsAt' => optional($contrato->data_fim)?->toDateString(),
                'daysLeft' => $contrato->data_fim
                    ? $now->diffInDays($contrato->data_fim, false)
                    : null,
                'alertKey' => sprintf('contract:%d:%s', $contrato->id, optional($contrato->data_fim)?->toDateString()),
            ]);

        $openInvoices = Fatura::query()
            ->with(['contrato.imovel'])
            ->where('status', 'Aberta')
            ->orderBy('vencimento')
            ->limit(5)
            ->get()
            ->map(fn (Fatura $fatura) => [
                'id' => $fatura->id,
                'competencia' => optional($fatura->competencia)?->toDateString(),
                'dueDate' => optional($fatura->vencimento)?->toDateString(),
                'contract' => $fatura->contrato?->codigo_contrato,
                'property' => $fatura->contrato?->imovel?->codigo,
                'amount' => (float) $fatura->valor_total,
                'lateDays' => $fatura->vencimento
                    ? $fatura->vencimento->diffInDays($now, false)
                    : null,
                'alertKey' => sprintf('invoice:%d:%s', $fatura->id, optional($fatura->vencimento)?->toDateString()),
            ]);

        $payablesTodayQuery = JournalEntry::query()
            ->with(['costCenter'])
            ->where('type', 'despesa')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->whereDate('due_date', $now->toDateString());

        $payablesTodaySummary = [
            'count' => (clone $payablesTodayQuery)->count(),
            'total' => (float) (clone $payablesTodayQuery)->sum('amount'),
        ];

        $payablesToday = (clone $payablesTodayQuery)
            ->orderBy('due_date')
            ->orderBy('movement_date')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(function (JournalEntry $entry) {
                $description = $entry->description_custom
                    ?? $entry->description?->texto
                    ?? $entry->notes
                    ?? sprintf('Lançamento %d', $entry->id);

                return [
                    'id' => $entry->id,
                    'description' => $description,
                    'amount' => (float) $entry->amount,
                    'dueDate' => optional($entry->due_date)?->toDateString(),
                    'status' => $entry->status,
                    'costCenter' => $entry->costCenter?->nome,
                    'link' => route('financeiro.entries.edit', $entry->id),
                ];
            });

        $readAlerts = $request->user()
            ? $request->user()->dismissedAlerts()->pluck('alert_key')->all()
            : [];

        $alerts = $this->buildAlerts($now, $expiringContracts, $openInvoices, $readAlerts);

        $widgets = $this->buildWidgets($request->user());

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'financialTrend' => $financialTrend,
            'delinquency' => $delinquency,
            'expiringContracts' => $expiringContracts,
            'openInvoices' => $openInvoices,
            'payablesToday' => $payablesToday,
            'payablesTodaySummary' => $payablesTodaySummary,
            'alerts' => $alerts,
            'widgets' => $widgets,
        ]);
    }

    public const WIDGET_DEFINITIONS = [
        'metrics' => 'Indicadores gerais',
        'financial_overview' => 'Faturamento x recebimentos',
        'delinquency' => 'Inadimplência',
        'payables_today' => 'Contas a pagar (hoje)',
        'expiring_contracts' => 'Contratos a vencer',
        'open_invoices' => 'Faturas em aberto',
    ];

    /**
     * @param  \Illuminate\Support\Carbon  $now
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $expiringContracts
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $openInvoices
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(Carbon $now, $expiringContracts, $openInvoices, array $readAlerts): array
    {
        $readSet = collect($readAlerts)->filter()->unique()->values();

        $contractCollection = $expiringContracts instanceof EloquentCollection
            ? $expiringContracts->toBase()
            : Collection::make($expiringContracts);

        $invoiceCollection = $openInvoices instanceof EloquentCollection
            ? $openInvoices->toBase()
            : Collection::make($openInvoices);

        $contractAlerts = $contractCollection
            ->filter(function (array $contract) {
                $daysLeft = $contract['daysLeft'];

                return $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 7;
            })
            ->reject(fn (array $contract) => $readSet->contains($contract['alertKey'] ?? ''))
            ->map(function (array $contract) use ($now) {
                $daysLeft = (int) $contract['daysLeft'];
                $key = $contract['alertKey'] ?? sprintf('contract:%d:%s', $contract['id'], $contract['endsAt'] ?? $now->toDateString());
                $severity = $daysLeft <= 0 ? 'danger' : ($daysLeft <= 3 ? 'warning' : 'info');

                return [
                    'type' => $severity,
                    'category' => 'contract.expiring',
                    'title' => 'Contrato próximo do vencimento',
                    'message' => sprintf(
                        'Contrato %s vence %s.',
                        $contract['code'] ?? '#'.$contract['id'],
                        $daysLeft === 0 ? 'hoje' : "em {$daysLeft} dia(s)"
                    ),
                    'action' => [
                        'label' => 'Ver contrato',
                        'href' => route('contratos.show', $contract['id']),
                    ],
                    'resource' => [
                        'type' => Contrato::class,
                        'id' => $contract['id'],
                    ],
                    'payload' => [
                        'code' => $contract['code'] ?? null,
                        'ends_at' => $contract['endsAt'] ?? null,
                        'days_left' => $contract['daysLeft'],
                    ],
                    'occurred_at' => $contract['endsAt'] ?? $now->toDateString(),
                    'key' => $key,
                ];
            });

        $invoiceAlerts = $invoiceCollection
            ->reject(fn (array $invoice) => $readSet->contains($invoice['alertKey'] ?? ''))
            ->map(function (array $invoice) use ($now) {
                if (! $invoice['dueDate']) {
                    return null;
                }

                $dueDate = Carbon::parse($invoice['dueDate']);
                $difference = $dueDate->diffInDays($now, false);

                if ($difference < -7) {
                    return null;
                }

                $key = $invoice['alertKey'] ?? sprintf('invoice:%d', $invoice['id']);
                $category = $difference > 0 ? 'invoice.overdue' : 'invoice.due_soon';

                if ($difference > 0) {
                    return [
                        'type' => 'danger',
                        'category' => $category,
                        'title' => 'Fatura em atraso',
                        'message' => sprintf(
                            'Fatura %s do contrato %s está atrasada há %d dia(s).',
                            $invoice['id'],
                            $invoice['contract'] ?? '#'.$invoice['id'],
                            $difference
                        ),
                        'action' => [
                            'label' => 'Ver fatura',
                            'href' => route('faturas.show', $invoice['id']),
                        ],
                        'resource' => [
                            'type' => Fatura::class,
                            'id' => $invoice['id'],
                        ],
                        'payload' => [
                            'contract' => $invoice['contract'] ?? null,
                            'due_date' => $invoice['dueDate'],
                            'late_days' => $difference,
                        ],
                        'occurred_at' => $invoice['dueDate'],
                        'key' => $key,
                    ];
                }

                if ($difference >= -7) {
                    $days = abs($difference);

                    return [
                        'type' => $days <= 2 ? 'warning' : 'info',
                        'category' => $category,
                        'title' => 'Fatura próxima do vencimento',
                        'message' => sprintf(
                            'Fatura %s do contrato %s vence em %d dia(s).',
                            $invoice['id'],
                            $invoice['contract'] ?? '#'.$invoice['id'],
                            $days
                        ),
                        'action' => [
                            'label' => 'Ver fatura',
                            'href' => route('faturas.show', $invoice['id']),
                        ],
                        'resource' => [
                            'type' => Fatura::class,
                            'id' => $invoice['id'],
                        ],
                        'payload' => [
                            'contract' => $invoice['contract'] ?? null,
                            'due_date' => $invoice['dueDate'],
                            'days_until_due' => $days,
                        ],
                        'occurred_at' => $invoice['dueDate'],
                        'key' => $key,
                    ];
                }

                return null;
            })
            ->filter();

        $alerts = $contractAlerts->merge($invoiceAlerts)->values();

        return $this->recordAlerts($alerts)->take(10)->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFinancialTrend(Carbon $now): array
    {
        $monthsBack = 5;
        $start = $now->copy()->subMonths($monthsBack)->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $period = CarbonPeriod::create($start, '1 month', $end);

        $trend = collect($period)->mapWithKeys(function (Carbon $date) {
            $key = $date->format('Y-m');
            $label = $date->locale(config('app.locale', 'pt_BR'))->isoFormat('MMM[/]YYYY');
            $label = Str::of($label)->title()->value();

            return [
                $key => [
                    'key' => $key,
                    'label' => $label,
                    'billed' => 0.0,
                    'received' => 0.0,
                ],
            ];
        })->toArray();

        $issuedFaturas = Fatura::query()
            ->select(['competencia', 'created_at', 'valor_total'])
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('competencia', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($inner) use ($start, $end): void {
                        $inner->whereNull('competencia')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->get();

        foreach ($issuedFaturas as $fatura) {
            $issueDate = $fatura->competencia ?? $fatura->created_at;
            if (! $issueDate instanceof Carbon) {
                continue;
            }

            $bucket = $issueDate->copy()->startOfMonth()->format('Y-m');

            if (! array_key_exists($bucket, $trend)) {
                continue;
            }

            $trend[$bucket]['billed'] += (float) $fatura->valor_total;
        }

        $paidFaturas = Fatura::query()
            ->select(['pago_em', 'valor_pago', 'valor_total'])
            ->where('status', 'Paga')
            ->whereBetween('pago_em', [$start->toDateString(), $end->toDateString()])
            ->get();

        foreach ($paidFaturas as $fatura) {
            if (! $fatura->pago_em instanceof Carbon) {
                continue;
            }

            $bucket = $fatura->pago_em->copy()->startOfMonth()->format('Y-m');

            if (! array_key_exists($bucket, $trend)) {
                continue;
            }

            $amount = (float) ($fatura->valor_pago ?? $fatura->valor_total ?? 0);
            $trend[$bucket]['received'] += $amount;
        }

        return array_values($trend);
    }

    private function buildDelinquencySummary(float $openAmount, float $paidThisMonth): array
    {
        $totalReceivables = max($openAmount + $paidThisMonth, 0.0);
        $rate = $totalReceivables > 0
            ? round(($openAmount / $totalReceivables) * 100, 2)
            : 0.0;

        return [
            'openAmount' => $openAmount,
            'paidThisMonth' => $paidThisMonth,
            'rate' => $rate,
        ];
    }

    private function buildWidgets($user): array
    {
        $definitions = collect(self::WIDGET_DEFINITIONS)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'hidden' => false,
            'position' => 0,
        ]);

        $userWidgets = $user
            ? $user->dashboardWidgets()->get()->keyBy('widget_key')
            : collect();

        $widgets = $definitions->map(function (array $definition, string $key) use ($userWidgets) {
            $preference = $userWidgets->get($key);

            return [
                'key' => $key,
                'label' => $definition['label'],
                'hidden' => (bool) optional($preference)->hidden,
                'position' => optional($preference)->position ?? array_search($key, array_keys(self::WIDGET_DEFINITIONS), true),
            ];
        })->values();

        $visible = $widgets->filter(fn ($widget) => ! $widget['hidden'])->sortBy('position')->values();
        $hidden = $widgets->filter(fn ($widget) => $widget['hidden'])->sortBy('position')->values();

        $ordered = $visible->concat($hidden)->values()->map(function ($widget, $index) {
            $widget['position'] = $index;

            return $widget;
        });

        return $ordered->all();
    }

    private function recordAlerts(Collection $alerts): Collection
    {
        if ($alerts->isEmpty()) {
            return $alerts;
        }

        $keys = $alerts->pluck('key')->filter()->unique()->all();

        if ($keys === []) {
            return $alerts;
        }

        $existing = DashboardAlert::query()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $now = now();

        foreach ($alerts as $alert) {
            $occurred = $alert['occurred_at'] ?? null;
            if ($occurred instanceof Carbon) {
                $occurredAt = $occurred;
            } elseif ($occurred) {
                $occurredAt = Carbon::parse((string) $occurred);
            } else {
                $occurredAt = $now->copy();
            }

            $attributes = [
                'category' => $alert['category'] ?? 'general',
                'severity' => $alert['type'] ?? 'info',
                'title' => $alert['title'] ?? '',
                'message' => $alert['message'] ?? '',
                'resource_type' => $alert['resource']['type'] ?? null,
                'resource_id' => $alert['resource']['id'] ?? null,
                'payload' => $alert['payload'] ?? [],
            ];

            /** @var \App\Models\DashboardAlert|null $record */
            $record = $existing->get($alert['key']);

            if ($record) {
                $record->fill($attributes);
                if (! $record->occurred_at || $record->occurred_at->greaterThan($occurredAt)) {
                    $record->occurred_at = $occurredAt;
                }
                $record->save();
            } else {
                DashboardAlert::create(array_merge([
                    'key' => $alert['key'],
                    'occurred_at' => $occurredAt,
                ], $attributes));
            }
        }

        $resolvedKeys = DashboardAlert::query()
            ->whereIn('key', $keys)
            ->whereNotNull('resolved_at')
            ->pluck('key')
            ->all();

        if ($resolvedKeys === []) {
            return $alerts->values();
        }

        return $alerts->reject(fn (array $alert) => in_array($alert['key'], $resolvedKeys, true))->values();
    }
}
