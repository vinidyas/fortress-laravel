<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\BankAccountStatementRequest;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class BankAccountStatementController extends Controller
{
    public function index(BankAccountStatementRequest $request): JsonResponse
    {
        $account = FinancialAccount::query()->findOrFail($request->integer('financial_account_id'));

        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->toDateString() : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->toDateString() : null;

        $openingBalanceBase = (float) ($account->saldo_inicial ?? 0);

        $openingBalance = $request->input('opening_balance');

        if ($openingBalance === null) {
            $openingAdjust = $this->baseQuery($account->id)
                ->when($dateFrom, fn ($query) => $query->whereDate('movement_date', '<', $dateFrom))
                ->get()
                ->sum(fn (JournalEntry $entry) => $this->resolveSignedAmountForAccount($entry, $account->id));

            $openingBalance = $openingBalanceBase + $openingAdjust;
        }

        $entries = $this->baseQuery($account->id)
            ->when($dateFrom, fn ($query) => $query->whereDate('movement_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('movement_date', '<=', $dateTo))
            ->with([
                'person:id,nome_razao_social',
                'property:id,codigo,logradouro,numero,bairro,cidade,complemento',
            ])
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $statementRows = $this->buildRows($entries, $openingBalance, $account->id);

        $totals = [
            'inflow' => round(array_sum(array_column($statementRows, 'amount_in')), 2),
            'outflow' => round(array_sum(array_column($statementRows, 'amount_out')), 2),
            'net' => round(array_sum(array_column($statementRows, 'signed_amount')), 2),
        ];

        $closingBalance = ! empty($statementRows)
            ? end($statementRows)['balance_after']
            : round($openingBalance, 2);

        return response()->json([
            'account' => [
                'id' => $account->id,
                'nome' => $account->nome,
                'saldo_inicial' => round($account->saldo_inicial ?? 0, 2),
                'data_saldo_inicial' => optional($account->data_saldo_inicial)->toDateString(),
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'opening_balance' => round($openingBalance, 2),
            'opening_balance_base' => round($openingBalanceBase, 2),
            'closing_balance' => round($closingBalance, 2),
            'totals' => $totals,
            'data' => $statementRows,
        ]);
    }

    /**
     * @return Collection<int,JournalEntry>
     */
    private function baseQuery(int $accountId)
    {
        return JournalEntry::query()
            ->where(function ($query) use ($accountId) {
                $query
                    ->where('bank_account_id', $accountId)
                    ->orWhere('counter_bank_account_id', $accountId);
            });
    }

    /**
     * @param  Collection<int,JournalEntry>  $entries
     * @return array<int,array<string,mixed>>
     */
    private function buildRows(Collection $entries, float $openingBalance, int $accountId): array
    {
        $running = $openingBalance;

        return $entries->map(function (JournalEntry $entry) use (&$running, $accountId) {
            $signed = $this->resolveSignedAmountForAccount($entry, $accountId);
            $running += $signed;

            return [
                'id' => $entry->id,
                'movement_date' => $entry->movement_date?->toDateString(),
                'due_date' => $entry->due_date?->toDateString(),
                'description' => $entry->description_custom ?? $entry->description_id,
                'type' => $entry->type,
                'amount' => round((float) $entry->amount, 2),
                'signed_amount' => round($signed, 2),
                'absolute_amount' => round(abs($signed), 2),
                'amount_in' => $signed > 0 ? round($signed, 2) : 0.0,
                'amount_out' => $signed < 0 ? round(abs($signed), 2) : 0.0,
                'balance_after' => round($running, 2),
                'person' => $entry->person
                    ? [
                        'id' => $entry->person->id,
                        'nome' => $entry->person->nome,
                    ]
                    : null,
                'property' => $entry->property
                    ? [
                        'id' => $entry->property->id,
                        'nome' => $this->resolvePropertyLabel($entry),
                    ]
                    : null,
            ];
        })->toArray();
    }

    private function resolveSignedAmountForAccount(JournalEntry $entry, int $accountId): float
    {
        $amount = (float) $entry->amount;

        if ((int) $entry->bank_account_id === $accountId) {
            return match ($entry->type) {
                'receita' => $amount,
                'despesa' => -$amount,
                'transferencia' => -$amount,
                default => 0.0,
            };
        }

        if ((int) $entry->counter_bank_account_id === $accountId) {
            return match ($entry->type) {
                'transferencia' => $amount,
                default => $amount,
            };
        }

        return 0.0;
    }

    private function resolvePropertyLabel(JournalEntry $entry): ?string
    {
        $property = $entry->property;

        if (! $property) {
            return null;
        }

        $segments = [];

        if (! empty($property->complemento)) {
            $segments[] = trim((string) $property->complemento);
        }

        if (! empty($property->logradouro)) {
            $logradouro = trim((string) $property->logradouro);
            if (! empty($property->numero)) {
                $logradouro = trim($logradouro.' '.$property->numero);
            }
            $segments[] = $logradouro;
        }

        if (! empty($property->bairro)) {
            $segments[] = trim((string) $property->bairro);
        }

        if (! empty($property->cidade)) {
            $segments[] = trim((string) $property->cidade);
        }

        if (empty($segments) && ! empty($property->codigo)) {
            $segments[] = trim((string) $property->codigo);
        }

        $label = trim(implode(' • ', array_filter($segments)));

        return $label !== '' ? $label : ($property->codigo ?? null);
    }
}
