<?php

declare(strict_types=1);

namespace App\Enums;

enum ContratoTipo: string
{
    case Residencial = 'Residencial';
    case Comercial = 'Comercial';
    case Temporada = 'Temporada';
    case Outros = 'Outros';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
