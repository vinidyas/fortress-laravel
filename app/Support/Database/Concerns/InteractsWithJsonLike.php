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

    protected function jsonValueExpression(Builder $query, string $column, string $path): string
    {
        $grammar = $query->getQuery()->getGrammar();
        $wrappedColumn = $grammar->wrap($column);
        $driver = $query->getConnection()->getDriverName();

        $normalizedPath = ltrim($path, '$.');
        $segments = array_values(array_filter(explode('.', $normalizedPath), static fn ($segment) => $segment !== ''));
        $jsonPath = '$.'.implode('.', $segments);

        // SQLite and SQL Server accept the raw JSON path even for empty segments, so guard here.
        if ($jsonPath === '$.') {
            $jsonPath = '$';
        }

        $escapedSegments = array_map(static fn ($segment) => str_replace("'", "''", $segment), $segments);

        return match ($driver) {
            'mysql' => "JSON_UNQUOTE(JSON_EXTRACT({$wrappedColumn}, '{$jsonPath}'))",
            'sqlite' => "json_extract({$wrappedColumn}, '{$jsonPath}')",
            'pgsql' => count($segments) <= 1
                ? "{$wrappedColumn} ->> '".($escapedSegments[0] ?? '')."'"
                : "{$wrappedColumn} #>> '{".implode(',', $escapedSegments)."}'",
            'sqlsrv' => "JSON_VALUE({$wrappedColumn}, '{$jsonPath}')",
            default => "json_extract({$wrappedColumn}, '{$jsonPath}')",
        };
    }
}
