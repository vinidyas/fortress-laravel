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

    public function label(): string
    {
        return match ($this) {
            self::Boleto => 'Boleto',
            self::Pix => 'Pix',
            self::Deposito => 'Depósito',
            self::Transferencia => 'Transferência',
            self::CartaoCredito => 'Cartão de crédito',
            self::Dinheiro => 'Dinheiro',
        };
    }
}
