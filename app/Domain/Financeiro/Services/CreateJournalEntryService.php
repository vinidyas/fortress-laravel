<?php

namespace App\Domain\Financeiro\Services;

use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Services\JournalEntryStateService;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

class CreateJournalEntryService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly JournalEntryStateService $stateService,
    ) {
    }

    public function handle(JournalEntryData $data): JournalEntry
    {
        return $this->database->transaction(function () use ($data) {
            $this->assertAccountSupportsCurrency($data->bankAccountId, $data->currency);
            $this->assertTransferConsistency($data);

            /** @var JournalEntry $entry */
            $entry = JournalEntry::query()->create([
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
                'origin' => $data->origin,
                'clone_of_id' => $data->cloneOfId,
                'movement_date' => $data->movementDate,
                'due_date' => $data->dueDate,
                'payment_date' => $data->paymentDate,
                'amount' => $data->amount,
                'currency' => $data->currency,
                'status' => $data->status->value,
                'created_by' => $data->createdBy,
                'updated_by' => $data->updatedBy,
            ]);

            $entry->installments()->createMany(
                $data->installments->map(fn ($installment) => [
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
                ])->all()
            );

            if ($data->allocations->isNotEmpty()) {
                $entry->allocations()->createMany(
                    $data->allocations->map(fn ($allocation) => [
                        'cost_center_id' => $allocation->costCenterId,
                        'property_id' => $allocation->propertyId,
                        'percentage' => $allocation->percentage,
                        'amount' => $allocation->amount,
                    ])->all()
                );
            }

            $entry = $this->stateService->sync($entry, JournalEntryStatus::Planejado);

            return $entry->load(['bankAccount', 'costCenter.parent', 'installments', 'allocations', 'attachments', 'receipts']);
        });
    }

    private function assertAccountSupportsCurrency(int $accountId, string $currency): void
    {
        $account = FinancialAccount::query()->findOrFail($accountId);

        if ($account->moeda !== $currency) {
            throw new InvalidArgumentException("Conta {$account->id} opera em {$account->moeda} e não suporta {$currency}.");
        }
    }

    private function assertTransferConsistency(JournalEntryData $data): void
    {
        if (! $data->type->isTransfer()) {
            return;
        }

        if (! $data->counterBankAccountId) {
            throw new InvalidArgumentException('Transferência precisa informar conta destino.');
        }

        if ($data->bankAccountId === $data->counterBankAccountId) {
            throw new InvalidArgumentException('Transferência não pode usar a mesma conta na origem e destino.');
        }
    }
}
