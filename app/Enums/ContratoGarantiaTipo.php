<?php

declare(strict_types=1);

namespace App\Enums;

enum ContratoGarantiaTipo: string
{
    case Fiador = 'Fiador';
    case Seguro = 'Seguro';
    case Caucao = 'Caucao';
    case SemGarantia = 'SemGarantia';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
