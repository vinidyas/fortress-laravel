<?php

declare(strict_types=1);

namespace App\Enums;

enum ContratoStatus: string
{
    case Ativo = 'Ativo';
    case Suspenso = 'Suspenso';
    case Encerrado = 'Encerrado';
    case Rescindido = 'Rescindido';
    case EmAnalise = 'EmAnalise';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
