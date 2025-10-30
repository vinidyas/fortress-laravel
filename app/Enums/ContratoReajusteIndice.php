<?php

declare(strict_types=1);

namespace App\Enums;

enum ContratoReajusteIndice: string
{
    case IGPM = 'IGPM';
    case IGPDI = 'IGPDI';
    case IPCA = 'IPCA';
    case IPCA15 = 'IPCA15';
    case INPC = 'INPC';
    case TR = 'TR';
    case SELIC = 'SELIC';
    case Outro = 'OUTRO';
    case SemReajuste = 'SEM_REAJUSTE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
