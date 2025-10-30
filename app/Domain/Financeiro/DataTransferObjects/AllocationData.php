<?php

namespace App\Domain\Financeiro\DataTransferObjects;

class AllocationData
{
    public function __construct(
        public readonly int $costCenterId,
        public readonly ?int $propertyId,
        public readonly ?float $percentage,
        public readonly ?float $amount,
    ) {
    }
}
