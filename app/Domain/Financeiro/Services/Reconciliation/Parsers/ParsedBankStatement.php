<?php

namespace App\Domain\Financeiro\Services\Reconciliation\Parsers;

class ParsedBankStatement
{
    /**
     * @param  array<int,array<string,mixed>>  $lines
     * @param  array<string,mixed>  $meta
     */
    public function __construct(
        public readonly string $reference,
        public readonly array $lines,
        public readonly array $meta = [],
    ) {
    }
}
