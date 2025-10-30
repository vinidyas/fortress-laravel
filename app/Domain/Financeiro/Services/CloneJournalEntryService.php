<?php

namespace App\Domain\Financeiro\Services;

use App\Domain\Financeiro\DataTransferObjects\AllocationData;
use App\Domain\Financeiro\DataTransferObjects\InstallmentData;
use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CloneJournalEntryService
{
    public function __construct(private readonly CreateJournalEntryService $creator)
    {
    }

    public function handle(JournalEntry $entry, array $overrides = []): JournalEntry
    {
        if ($entry->status !== JournalEntryStatus::Pago->value && $entry->status !== JournalEntryStatus::Pendente->value) {
            throw new InvalidArgumentException('Somente lançamentos pagos ou pendentes podem ser clonados.');
        }

        $entry->loadMissing(['installments', 'allocations']);

        $installments = $entry->installments
            ->map(fn ($installment) => new InstallmentData(
                numeroParcela: $installment->numero_parcela,
                movementDate: $installment->movement_date->toDateString(),
                dueDate: $installment->due_date->toDateString(),
                paymentDate: null,
                valorPrincipal: (float) $installment->valor_principal,
                valorJuros: (float) $installment->valor_juros,
                valorMulta: 0,
                valorDesconto: 0,
                valorTotal: (float) $installment->valor_principal,
                status: JournalEntryStatus::Planejado,
                meta: null,
            ))
            ->values();

        $allocations = $entry->allocations->map(fn ($allocation) => new AllocationData(
            costCenterId: $allocation->cost_center_id,
            propertyId: $allocation->property_id,
            percentage: $allocation->percentage ? (float) $allocation->percentage : null,
            amount: $allocation->amount ? (float) $allocation->amount : null,
        ));

        $data = new JournalEntryData(
            type: JournalEntryType::from($entry->type),
            bankAccountId: $overrides['bank_account_id'] ?? $entry->bank_account_id,
            counterBankAccountId: $overrides['counter_bank_account_id'] ?? $entry->counter_bank_account_id,
            costCenterId: $overrides['cost_center_id'] ?? $entry->cost_center_id,
            propertyId: $overrides['property_id'] ?? $entry->property_id,
            personId: $overrides['person_id'] ?? $entry->person_id,
            descriptionId: $entry->description_id,
            descriptionCustom: $entry->description_custom,
            notes: $entry->notes,
            referenceCode: $overrides['reference_code'] ?? $entry->reference_code,
            origin: 'clonado',
            cloneOfId: $entry->id,
            movementDate: $overrides['movement_date'] ?? $entry->movement_date->toDateString(),
            dueDate: $overrides['due_date'] ?? $entry->due_date?->toDateString(),
            paymentDate: null,
            currency: $entry->currency,
            status: JournalEntryStatus::Planejado,
            amount: (float) $entry->amount,
            installments: Collection::make($installments),
            allocations: Collection::make($allocations),
            createdBy: $overrides['created_by'] ?? $entry->created_by,
            updatedBy: $overrides['updated_by'] ?? $entry->updated_by,
        );

        return $this->creator->handle($data);
    }
}
