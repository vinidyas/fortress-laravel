<?php

namespace App\Domain\Financeiro\Services;

use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Services\JournalEntryStateService;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Models\JournalEntry;
use Illuminate\Database\DatabaseManager;

class UpdateJournalEntryService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly JournalEntryStateService $stateService,
    ) {
    }

    public function handle(JournalEntry $entry, JournalEntryData $data): JournalEntry
    {
        return $this->database->transaction(function () use ($entry, $data) {
            $previousStatus = JournalEntryStatus::from($entry->status);

            $entry->fill([
                'type' => $data->type->value,
                'bank_account_id' => $data->bankAccountId,
                'counter_bank_account_id' => $data->counterBankAccountId,
                'cost_center_id' => $data->costCenterId,
                'property_id' => $data->propertyId,
                'person_id' => $data->personId,
                'description_id' => $data->descriptionId,
                'description_custom' => $data->descriptionCustom,
                'notes' => $data->notes,
                'reference_code' => $data->referenceCode,
                'movement_date' => $data->movementDate,
                'due_date' => $data->dueDate,
                'payment_date' => $data->paymentDate,
                'amount' => $data->amount,
                'currency' => $data->currency,
                'status' => $data->status->value,
            ]);

            $entry->save();

            $entry->installments()->delete();
            $entry->allocations()->delete();

            $entry->installments()->createMany($data->installments->map(fn ($installment) => [
                'numero_parcela' => $installment->numeroParcela,
                'movement_date' => $installment->movementDate,
                'due_date' => $installment->dueDate,
                'payment_date' => $installment->paymentDate,
                'valor_principal' => $installment->valorPrincipal,
                'valor_juros' => $installment->valorJuros,
                'valor_multa' => $installment->valorMulta,
                'valor_desconto' => $installment->valorDesconto,
                'valor_total' => $installment->valorTotal,
                'status' => $installment->status->value,
                'meta' => $installment->meta,
            ])->all());

            if ($data->allocations->isNotEmpty()) {
                $entry->allocations()->createMany($data->allocations->map(fn ($allocation) => [
                    'cost_center_id' => $allocation->costCenterId,
                    'property_id' => $allocation->propertyId,
                    'percentage' => $allocation->percentage,
                    'amount' => $allocation->amount,
                ])->all());
            }

            $entry = $this->stateService->sync($entry, $previousStatus);

            return $entry->load(['bankAccount', 'costCenter.parent', 'installments', 'allocations', 'attachments', 'receipts']);
        });
    }
}
