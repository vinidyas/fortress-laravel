<?php

declare(strict_types=1);

namespace App\Enums;

enum ContratoFormaPagamento: string
{
    case Boleto = 'Boleto';
    case Pix = 'Pix';
    case Deposito = 'Deposito';
    case Transferencia = 'Transferencia';
    case CartaoCredito = 'CartaoCredito';
    case Dinheiro = 'Dinheiro';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
