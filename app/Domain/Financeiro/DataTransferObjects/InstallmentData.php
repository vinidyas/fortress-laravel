<?php

namespace App\Domain\Financeiro\DataTransferObjects;

use App\Domain\Financeiro\Support\JournalEntryStatus;

class InstallmentData
{
    public function __construct(
        public readonly int $numeroParcela,
        public readonly string $movementDate,
        public readonly string $dueDate,
        public readonly ?string $paymentDate,
        public readonly float $valorPrincipal,
        public readonly float $valorJuros,
        public readonly float $valorMulta,
        public readonly float $valorDesconto,
        public readonly float $valorTotal,
        public readonly JournalEntryStatus $status,
        public readonly ?array $meta = null,
    ) {
    }
}
