<?php

declare(strict_types=1);

namespace App\Support\Database\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait InteractsWithJsonLike
{
    protected function orWhereJsonContainsLike(Builder $query, string $column, string $term): void
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orWhereRaw("LOWER(CAST({$wrapped} AS CHAR)) LIKE ?", [$term]);

            return;
        }

        if ($driver === 'sqlite') {
            $query->orWhereRaw("LOWER(CAST({$wrapped} AS TEXT)) LIKE ?", [$term]);

            return;
        }

        $query->orWhereRaw("LOWER({$wrapped}) LIKE ?", [$term]);
    }
}
