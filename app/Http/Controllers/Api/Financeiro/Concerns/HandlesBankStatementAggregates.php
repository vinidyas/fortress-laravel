<?php

namespace App\Http\Controllers\Api\Financeiro\Concerns;

use App\Models\BankStatement;
use Illuminate\Database\Eloquent\Builder;

trait HandlesBankStatementAggregates
{
    /**
     * @param  Builder<BankStatement>  $query
     * @return Builder<BankStatement>
     */
    protected function applyAggregatesToQuery(Builder $query): Builder
    {
        return $query
            ->with(['account:id,nome', 'importedByUser:id,nome,username'])
            ->withCount($this->lineCountDefinitions())
            ->withSum($this->lineSumDefinitions(), 'amount');
    }

    protected function applyAggregatesToModel(BankStatement $statement): BankStatement
    {
        $statement->loadMissing(['account:id,nome', 'importedByUser:id,nome,username']);
        $statement->loadCount($this->lineCountDefinitions());
        $statement->loadSum($this->lineSumDefinitions(), 'amount');

        return $statement;
    }

    /**
     * @return array<int|string,mixed>
     */
    protected function lineCountDefinitions(): array
    {
        return [
            'lines as total_lines_count',
            'lines as pending_lines_count' => fn ($query) => $query->whereIn('match_status', ['nao_casado', 'sugerido']),
            'lines as confirmed_lines_count' => fn ($query) => $query->where('match_status', 'confirmado'),
            'lines as suggested_lines_count' => fn ($query) => $query->where('match_status', 'sugerido'),
            'lines as ignored_lines_count' => fn ($query) => $query->where('match_status', 'ignorado'),
        ];
    }

    /**
     * @return array<int|string,mixed>
     */
    protected function lineSumDefinitions(): array
    {
        return [
            'lines as total_sum_amount',
            'lines as credit_sum_amount' => fn ($query) => $query->where('amount', '>', 0),
            'lines as debit_sum_amount' => fn ($query) => $query->where('amount', '<', 0),
        ];
    }
}
