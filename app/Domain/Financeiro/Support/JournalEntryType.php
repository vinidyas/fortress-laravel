<?php

namespace App\Domain\Financeiro\Support;

enum JournalEntryType: string
{
    case Receita = 'receita';
    case Despesa = 'despesa';
    case Transferencia = 'transferencia';

    public function isTransfer(): bool
    {
        return $this === self::Transferencia;
    }
}
