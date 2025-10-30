<?php

namespace App\Domain\Financeiro\Support;

enum BankStatementStatus: string
{
    case Processando = 'processando';
    case Importado = 'importado';
    case Conciliado = 'conciliado';
    case Erro = 'erro';

    public function label(): string
    {
        return match ($this) {
            self::Processando => 'Processando',
            self::Importado => 'Importado',
            self::Conciliado => 'Conciliado',
            self::Erro => 'Erro',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Processando => 'processing',
            self::Importado => 'open',
            self::Conciliado => 'reconciled',
            self::Erro => 'error',
        };
    }

    /**
     * @return array<int,string>
     */
    public static function filterValues(string $value): array
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'open', 'pendente', 'pendentes' => [
                self::Processando->value,
                self::Importado->value,
            ],
            'processing', 'processando' => [self::Processando->value],
            'reconciled', 'conciliado', 'conciliada', 'fechado', 'fechados', 'closed' => [self::Conciliado->value],
            'error', 'erro', 'falha', 'failed' => [self::Erro->value],
            'importado', 'importados' => [self::Importado->value],
            default => self::tryFrom($normalized)
                ? [self::tryFrom($normalized)->value]
                : [$value],
        };
    }
}
