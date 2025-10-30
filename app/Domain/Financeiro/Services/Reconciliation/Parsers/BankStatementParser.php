<?php

namespace App\Domain\Financeiro\Services\Reconciliation\Parsers;

interface BankStatementParser
{
    public function supports(string $extension, string $mimeType): bool;

    /**
     * @throws \RuntimeException
     */
    public function parse(string $contents, string $fileName): ParsedBankStatement;
}
