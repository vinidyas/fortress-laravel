<?php

namespace App\Domain\Financeiro\Support;

enum BankStatementLineStatus: string
{
    case NaoCasado = 'nao_casado';
    case Sugerido = 'sugerido';
    case Confirmado = 'confirmado';
    case Ignorado = 'ignorado';

    public function label(): string
    {
        return match ($this) {
            self::NaoCasado => 'Pendente',
            self::Sugerido => 'Sugestão',
            self::Confirmado => 'Confirmado',
            self::Ignorado => 'Ignorado',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Confirmado => 'matched',
            self::Ignorado => 'ignored',
            self::Sugerido => 'suggested',
            self::NaoCasado => 'open',
        };
    }
}
