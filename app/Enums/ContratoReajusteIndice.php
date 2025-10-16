<?php

declare(strict_types=1);

namespace App\Enums;

enum ContratoReajusteIndice: string
{
    case IGPM = 'IGPM';
    case IPCA = 'IPCA';
    case INPC = 'INPC';
    case SemReajuste = 'SEM_REAJUSTE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
