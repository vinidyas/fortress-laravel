<?php

declare(strict_types=1);

namespace App\Domain\Financeiro\Services;

use App\Models\DashboardAlert;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryInstallment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;

class AccountBalanceService
{
    private const CACHE_PREFIX = 'financeiro:account-balances';
    private const CACHE_VERSION_KEY = 'financeiro:account-balances:version';
    private const CACHE_TTL_SECONDS = 60;
    private const HISTORY_DAYS = 7;

    public function __construct(private readonly Repository $cache)
    {
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function getSummary(?User $user, array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        $version = $this->currentVersion();
        $cacheKey = $this->makeCacheKey((int) ($user?->id ?? 0), $normalizedFilters, $version);

        return $this->cache->remember(
            $cacheKey,
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn () => $this->computeSummary($normalizedFilters)
        );
    }

    /**
     * @param  array<int,int>|null  $accountIds
     */
    public function invalidateCache(?array $accountIds = null): void
    {
        $version = $this->currentVersion();
        $this->cache->put(
            self::CACHE_VERSION_KEY,
            $version + 1,
            now()->addMonths(6)
        );
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function computeSummary(array $filters): array
    {
        $accountsQuery = FinancialAccount::query()
            ->select([
                'id',
                'nome',
                'apelido',
                'categoria',
                'moeda',
                'saldo_inicial',
                'saldo_atual',
                'limite_credito',
                'integra_config',
                'ativo',
                'updated_at',
            ])
            ->when(! $filters['include_inactive'], fn ($query) => $query->where('ativo', true))
            ->when($filters['category'], fn ($query, $category) => $query->where('categoria', $category))
            ->when($filters['account_id'], fn ($query, $accountId) => $query->where('id', $accountId));

        if ($filters['cost_center_id']) {
            $relatedAccountIds = $this->resolveAccountIdsForCostCenter((int) $filters['cost_center_id']);

            if ($relatedAccountIds === []) {
                return $this->emptySummary($filters);
            }

            $accountsQuery->whereIn('id', $relatedAccountIds);
        }

        /** @var EloquentCollection<int, FinancialAccount> $accounts */
        $accounts = $accountsQuery->orderBy('nome')->get();

        if ($accounts->isEmpty()) {
            return $this->emptySummary($filters);
        }

        $accountIds = $accounts->pluck('id')->all();

        $scheduledTotals = $this->loadScheduledTotals($accountIds, $filters['cost_center_id']);
        $lastMovements = $this->loadLastMovements($accountIds, $filters['cost_center_id']);

        $accountsData = [];
        $totalCurrent = 0.0;
        $totalProjected = 0.0;
        $pendingDeltaTotal = 0.0;

        foreach ($accounts as $account) {
            $accountId = (int) $account->id;
            $scheduled = $scheduledTotals[$accountId] ?? ['incoming' => 0.0, 'outgoing' => 0.0];
            $incoming = (float) $scheduled['incoming'];
            $outgoing = (float) $scheduled['outgoing'];

            $current = $this->resolveCurrentBalance($account);
            $pendingDelta = $incoming - $outgoing;
            $projected = $current + $pendingDelta;

            $threshold = $this->resolveAlertThreshold($account);
            $alertActive = $threshold !== null && $current < $threshold;

            $accountData = [
                'id' => $accountId,
                'nome' => $account->nome,
                'apelido' => $account->apelido,
                'categoria' => $account->categoria,
                'moeda' => $account->moeda ?? 'BRL',
                'saldo_inicial' => round((float) ($account->saldo_inicial ?? 0), 2),
                'saldo_atual' => round($current, 2),
                'saldo_projetado' => round($projected, 2),
                'pendente_delta' => round($pendingDelta, 2),
                'pendente_entradas' => round($incoming, 2),
                'pendente_saidas' => round($outgoing, 2),
                'ultima_movimentacao' => $lastMovements[$accountId] ?? null,
                'alerta' => [
                    'ativo' => $alertActive,
                    'limite' => $threshold,
                ],
            ];

            $accountsData[] = $accountData;

            $totalCurrent += $accountData['saldo_atual'];
            $totalProjected += $accountData['saldo_projetado'];
            $pendingDeltaTotal += $accountData['pendente_delta'];
        }

        $summaryStatus = $totalCurrent > 0 ? 'positive' : ($totalCurrent < 0 ? 'negative' : 'neutral');

        $topPositive = collect($accountsData)
            ->sortByDesc('saldo_atual')
            ->filter(fn (array $account) => $account['saldo_atual'] > 0)
            ->take(3)
            ->values()
            ->all();

        $topNegative = collect($accountsData)
            ->sortBy('saldo_atual')
            ->filter(fn (array $account) => $account['saldo_atual'] < 0)
            ->take(3)
            ->values()
            ->all();

        $history = $this->buildHistory($accountIds, $totalCurrent, $filters['cost_center_id']);
        $alerts = $this->syncAlerts($accountsData);

        return [
            'summary' => [
                'total_current' => round($totalCurrent, 2),
                'total_projected' => round($totalProjected, 2),
                'pending_delta' => round($pendingDeltaTotal, 2),
                'status' => $summaryStatus,
            ],
            'top_positive' => $topPositive,
            'top_negative' => $topNegative,
            'accounts' => $accountsData,
            'history' => $history,
            'alerts' => $alerts,
            'meta' => [
                'available_categories' => $this->availableCategories(),
                'applied_filters' => $filters,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function emptySummary(array $filters): array
    {
        return [
            'summary' => [
                'total_current' => 0.0,
                'total_projected' => 0.0,
                'pending_delta' => 0.0,
                'status' => 'neutral',
            ],
            'top_positive' => [],
            'top_negative' => [],
            'accounts' => [],
            'history' => [
                'points' => [],
                'min' => 0.0,
                'max' => 0.0,
            ],
            'alerts' => [],
            'meta' => [
                'available_categories' => $this->availableCategories(),
                'applied_filters' => $filters,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildHistory(array $accountIds, float $currentTotal, ?int $costCenterId): array
    {
        if ($accountIds === []) {
            return [
                'points' => [],
                'min' => 0.0,
                'max' => 0.0,
            ];
        }

        $end = Carbon::today();
        $start = (clone $end)->subDays(self::HISTORY_DAYS - 1);

        $historyChanges = JournalEntryInstallment::query()
            ->selectRaw('journal_entry_installments.payment_date as payment_date, journal_entries.type, SUM(journal_entry_installments.valor_total) as total')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_installments.journal_entry_id')
            ->whereIn('journal_entries.bank_account_id', $accountIds)
            ->whereBetween('journal_entry_installments.payment_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('journal_entry_installments.payment_date')
            ->whereIn('journal_entries.type', ['receita', 'despesa'])
            ->where('journal_entries.status', '!=', 'cancelado')
            ->when($costCenterId, fn ($query, $centerId) => $query->where('journal_entries.cost_center_id', $centerId))
            ->groupBy('journal_entry_installments.payment_date', 'journal_entries.type')
            ->get()
            ->reduce(function (array $carry, $row) {
                $date = (string) $row->payment_date;
                $type = (string) $row->type;
                $total = (float) $row->total;

                $delta = $type === 'despesa' ? -$total : $total;
                $carry[$date] = ($carry[$date] ?? 0.0) + $delta;

                return $carry;
            }, []);

        $points = [];
        $running = $currentTotal;

        for ($date = clone $end; $date->greaterThanOrEqualTo($start); $date->subDay()) {
            $dateKey = $date->toDateString();
            $points[$dateKey] = round($running, 2);
            $running -= $historyChanges[$dateKey] ?? 0.0;
        }

        $ordered = array_reverse($points, true);
        $values = array_values($ordered);

        return [
            'points' => collect($ordered)
                ->map(fn ($balance, $date) => [
                    'date' => $date,
                    'balance' => round((float) $balance, 2),
                ])
                ->values()
                ->all(),
            'min' => $values === [] ? 0.0 : round(min($values), 2),
            'max' => $values === [] ? 0.0 : round(max($values), 2),
        ];
    }

    /**
     * @return array<int,array{incoming:float,outgoing:float}>
     */
    private function loadScheduledTotals(array $accountIds, ?int $costCenterId): array
    {
        if ($accountIds === []) {
            return [];
        }

        $pendingStatuses = ['planejado', 'pendente', 'atrasado'];

        $scheduled = [];

        $originRows = JournalEntryInstallment::query()
            ->selectRaw('journal_entries.bank_account_id as account_id, journal_entries.type, SUM(journal_entry_installments.valor_total) as total')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_installments.journal_entry_id')
            ->whereIn('journal_entries.bank_account_id', $accountIds)
            ->whereIn('journal_entry_installments.status', $pendingStatuses)
            ->where('journal_entries.status', '!=', 'cancelado')
            ->when($costCenterId, fn ($query, $centerId) => $query->where('journal_entries.cost_center_id', $centerId))
            ->groupBy('journal_entries.bank_account_id', 'journal_entries.type')
            ->get();

        foreach ($originRows as $row) {
            $accountId = (int) $row->account_id;
            $type = (string) $row->type;
            $total = (float) $row->total;

            $scheduled[$accountId] ??= ['incoming' => 0.0, 'outgoing' => 0.0];

            if ($type === 'receita') {
                $scheduled[$accountId]['incoming'] += $total;
            } else {
                $scheduled[$accountId]['outgoing'] += $total;
            }
        }

        $destinationRows = JournalEntryInstallment::query()
            ->selectRaw('journal_entries.counter_bank_account_id as account_id, SUM(journal_entry_installments.valor_total) as total')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_installments.journal_entry_id')
            ->where('journal_entries.type', 'transferencia')
            ->whereNotNull('journal_entries.counter_bank_account_id')
            ->whereIn('journal_entries.counter_bank_account_id', $accountIds)
            ->whereIn('journal_entry_installments.status', $pendingStatuses)
            ->where('journal_entries.status', '!=', 'cancelado')
            ->when($costCenterId, fn ($query, $centerId) => $query->where('journal_entries.cost_center_id', $centerId))
            ->groupBy('journal_entries.counter_bank_account_id')
            ->get();

        foreach ($destinationRows as $row) {
            $accountId = (int) $row->account_id;
            $total = (float) $row->total;

            $scheduled[$accountId] ??= ['incoming' => 0.0, 'outgoing' => 0.0];
            $scheduled[$accountId]['incoming'] += $total;
        }

        return $scheduled;
    }

    /**
     * @return array<int,string>
     */
    private function loadLastMovements(array $accountIds, ?int $costCenterId): array
    {
        if ($accountIds === []) {
            return [];
        }

        $baseQuery = JournalEntryInstallment::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_installments.journal_entry_id')
            ->where('journal_entries.status', '!=', 'cancelado')
            ->when($costCenterId, fn ($query, $centerId) => $query->where('journal_entries.cost_center_id', $centerId));

        $originDates = (clone $baseQuery)
            ->whereIn('journal_entries.bank_account_id', $accountIds)
            ->selectRaw('journal_entries.bank_account_id as account_id, MAX(COALESCE(journal_entry_installments.payment_date, journal_entry_installments.due_date, journal_entry_installments.movement_date)) as last_date')
            ->groupBy('journal_entries.bank_account_id')
            ->pluck('last_date', 'account_id')
            ->toArray();

        $destinationDates = (clone $baseQuery)
            ->where('journal_entries.type', 'transferencia')
            ->whereNotNull('journal_entries.counter_bank_account_id')
            ->whereIn('journal_entries.counter_bank_account_id', $accountIds)
            ->selectRaw('journal_entries.counter_bank_account_id as account_id, MAX(COALESCE(journal_entry_installments.payment_date, journal_entry_installments.due_date, journal_entry_installments.movement_date)) as last_date')
            ->groupBy('journal_entries.counter_bank_account_id')
            ->pluck('last_date', 'account_id')
            ->toArray();

        $result = [];

        foreach ($originDates as $accountId => $date) {
            if ($date) {
                $result[(int) $accountId] = Carbon::parse((string) $date)->toDateString();
            }
        }

        foreach ($destinationDates as $accountId => $date) {
            if (! $date) {
                continue;
            }

            $accountId = (int) $accountId;
            $current = $result[$accountId] ?? null;
            $candidate = Carbon::parse((string) $date);

            if ($current === null || $candidate->gt(Carbon::parse($current))) {
                $result[$accountId] = $candidate->toDateString();
            }
        }

        return $result;
    }

    /**
     * @return array<int,int>
     */
    private function resolveAccountIdsForCostCenter(int $costCenterId): array
    {
        $bankAccountIds = JournalEntry::query()
            ->where('cost_center_id', $costCenterId)
            ->whereNotNull('bank_account_id')
            ->where('status', '!=', 'cancelado')
            ->pluck('bank_account_id')
            ->all();

        $counterAccountIds = JournalEntry::query()
            ->where('cost_center_id', $costCenterId)
            ->where('type', 'transferencia')
            ->whereNotNull('counter_bank_account_id')
            ->where('status', '!=', 'cancelado')
            ->pluck('counter_bank_account_id')
            ->all();

        return array_values(array_unique(array_filter([
            ...$bankAccountIds,
            ...$counterAccountIds,
        ])));
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $category = isset($filters['category']) ? trim((string) $filters['category']) : null;
        if ($category === '' || $category === null) {
            $category = null;
        } else {
            $category = strtolower($category);
        }

        $costCenterId = isset($filters['cost_center_id']) ? (int) $filters['cost_center_id'] : null;
        if ($costCenterId !== null && $costCenterId <= 0) {
            $costCenterId = null;
        }

        $accountId = isset($filters['account_id']) ? (int) $filters['account_id'] : null;
        if ($accountId !== null && $accountId <= 0) {
            $accountId = null;
        }

        return [
            'category' => $category,
            'cost_center_id' => $costCenterId,
            'account_id' => $accountId,
            'include_inactive' => (bool) ($filters['include_inactive'] ?? false),
        ];
    }

    private function resolveCurrentBalance(FinancialAccount $account): float
    {
        $current = $account->saldo_atual;

        if ($current === null) {
            $current = $account->saldo_inicial;
        }

        return (float) ($current ?? 0);
    }

    private function resolveAlertThreshold(FinancialAccount $account): ?float
    {
        $config = $account->integra_config;

        $threshold = null;
        if (is_array($config)) {
            $threshold = Arr::get($config, 'alerts.low_balance_threshold');

            if ($threshold === null) {
                $threshold = Arr::get($config, 'alerts.threshold');
            }

            if ($threshold === null) {
                $threshold = Arr::get($config, 'low_balance_threshold');
            }

            if ($threshold === null) {
                $threshold = Arr::get($config, 'alert_threshold');
            }
        }

        if ($threshold === null && $account->limite_credito !== null) {
            $threshold = -abs((float) $account->limite_credito);
        }

        if ($threshold === null) {
            return null;
        }

        return is_numeric($threshold) ? (float) $threshold : null;
    }

    /**
     * @param  array<int,array<string,mixed>>  $accountsData
     * @return array<int,array<string,mixed>>
     */
    private function syncAlerts(array $accountsData): array
    {
        if ($accountsData === []) {
            return [];
        }

        $now = Carbon::now();

        $activeAlerts = collect($accountsData)
            ->filter(fn (array $account) => !empty($account['alerta']['ativo']))
            ->map(function (array $account) use ($now) {
                $threshold = (float) $account['alerta']['limite'];
                $current = (float) $account['saldo_atual'];
                $message = sprintf(
                    'A conta %s está com saldo de %s abaixo do limite configurado (%s).',
                    $account['nome'],
                    $this->formatCurrency($current),
                    $this->formatCurrency($threshold)
                );

                return [
                    'key' => sprintf('finance.balance:%d', $account['id']),
                    'account_id' => $account['id'],
                    'account_name' => $account['nome'],
                    'current_balance' => $current,
                    'threshold' => $threshold,
                    'message' => $message,
                    'occurred_at' => $now,
                ];
            })
            ->values();

        $activeKeys = $activeAlerts->pluck('key')->all();

        if ($activeAlerts->isNotEmpty()) {
            $existing = DashboardAlert::query()
                ->whereIn('key', $activeKeys)
                ->get()
                ->keyBy('key');

            foreach ($activeAlerts as $alert) {
                /** @var DashboardAlert|null $record */
                $record = $existing->get($alert['key']);
                $attributes = [
                    'category' => 'finance.balance',
                    'severity' => 'danger',
                    'title' => 'Conta abaixo do limite',
                    'message' => $alert['message'],
                    'resource_type' => FinancialAccount::class,
                    'resource_id' => $alert['account_id'],
                    'payload' => [
                        'threshold' => $alert['threshold'],
                        'current_balance' => $alert['current_balance'],
                    ],
                ];

                if ($record) {
                    $record->fill($attributes);
                    $record->occurred_at = $record->occurred_at && $record->occurred_at->lt($alert['occurred_at'])
                        ? $record->occurred_at
                        : $alert['occurred_at'];
                    $record->resolved_at = null;
                    $record->resolved_by = null;
                    $record->resolution_notes = null;
                    $record->save();
                } else {
                    DashboardAlert::create(array_merge([
                        'key' => $alert['key'],
                        'occurred_at' => $alert['occurred_at'],
                    ], $attributes));
                }
            }
        }

        DashboardAlert::query()
            ->where('category', 'finance.balance')
            ->whereIn('resource_id', collect($accountsData)->pluck('id')->all())
            ->when($activeKeys !== [], fn ($query) => $query->whereNotIn('key', $activeKeys))
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => $now,
            ]);

        return $activeAlerts->map(fn (array $alert) => [
            'account_id' => $alert['account_id'],
            'account_name' => $alert['account_name'],
            'current_balance' => round($alert['current_balance'], 2),
            'threshold' => round($alert['threshold'], 2),
            'message' => $alert['message'],
        ])->all();
    }

    private function availableCategories(): array
    {
        return FinancialAccount::query()
            ->select('categoria')
            ->distinct()
            ->pluck('categoria')
            ->filter()
            ->sort()
            ->values()
            ->all();
    }

    private function currentVersion(): int
    {
        return (int) $this->cache->get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function makeCacheKey(int $userId, array $filters, int $version): string
    {
        ksort($filters);

        return sprintf(
            '%s:v%s:u%s:%s',
            self::CACHE_PREFIX,
            $version,
            $userId,
            md5(json_encode($filters, JSON_THROW_ON_ERROR))
        );
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
