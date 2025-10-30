<?php

namespace App\Domain\Financeiro\DataTransferObjects;

use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use Illuminate\Support\Collection;

class JournalEntryData
{
    public function __construct(
        public readonly JournalEntryType $type,
        public readonly int $bankAccountId,
        public readonly ?int $counterBankAccountId,
        public readonly ?int $costCenterId,
        public readonly ?int $propertyId,
        public readonly ?int $personId,
        public readonly ?int $descriptionId,
        public readonly ?string $descriptionCustom,
        public readonly ?string $notes,
        public readonly ?string $referenceCode,
        public readonly string $origin,
        public readonly ?int $cloneOfId,
        public readonly string $movementDate,
        public readonly ?string $dueDate,
        public readonly ?string $paymentDate,
        public readonly string $currency,
        public readonly JournalEntryStatus $status,
        public readonly float $amount,
        /** @var Collection<int,InstallmentData> */
        public readonly Collection $installments,
        /** @var Collection<int,AllocationData> */
        public readonly Collection $allocations,
        public readonly ?int $createdBy = null,
        public readonly ?int $updatedBy = null,
    ) {
    }
}
