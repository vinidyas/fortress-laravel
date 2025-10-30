<?php

namespace App\Domain\Financeiro\Support;

use App\Domain\Financeiro\Support\JournalEntryType;

enum JournalEntryStatus: string
{
    case Planejado = 'planejado';
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Cancelado = 'cancelado';
    case Atrasado = 'atrasado';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Planejado => in_array($target, [self::Pendente, self::Atrasado, self::Pago, self::Cancelado], true),
            self::Pendente => in_array($target, [self::Pago, self::Cancelado, self::Atrasado], true),
            self::Atrasado => in_array($target, [self::Pago, self::Cancelado], true),
            self::Pago => $target === self::Cancelado,
            self::Cancelado => false,
        };
    }

    public function label(?JournalEntryType $type = null): string
    {
        if ($type === null) {
            return $this->defaultLabel();
        }

        if ($this === self::Cancelado) {
            return $type === JournalEntryType::Transferencia ? 'Cancelada' : 'Cancelado';
        }

        if ($this === self::Pago) {
            return match ($type) {
                JournalEntryType::Receita => 'Recebido',
                JournalEntryType::Despesa => 'Pago',
                JournalEntryType::Transferencia => 'Efetivada',
            };
        }

        return match ($type) {
            JournalEntryType::Receita => 'A receber',
            JournalEntryType::Despesa => 'A pagar',
            JournalEntryType::Transferencia => 'Pendente',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Pago => 'settled',
            self::Cancelado => 'cancelled',
            self::Atrasado => 'overdue',
            default => 'open',
        };
    }

    public static function filterValues(string $value): array
    {
        $normalized = strtolower(trim($value));

        $matched = self::tryFrom($normalized);

        return match ($normalized) {
            'open', 'aberto', 'em_aberto' => [
                self::Planejado->value,
                self::Pendente->value,
                self::Atrasado->value,
            ],
            'settled', 'paid', 'quitado', self::Pago->value => [self::Pago->value],
            'cancelled', 'canceled', 'cancelado', self::Cancelado->value => [self::Cancelado->value],
            'overdue', 'atrasado' => [self::Atrasado->value],
            'planejado' => [self::Planejado->value],
            'pendente' => [self::Pendente->value],
            default => $matched ? [$matched->value] : [$value],
        };
    }

    private function defaultLabel(): string
    {
        return match ($this) {
            self::Planejado => 'Planejado',
            self::Pendente => 'Pendente',
            self::Pago => 'Pago',
            self::Cancelado => 'Cancelado',
            self::Atrasado => 'Atrasado',
        };
    }
}
