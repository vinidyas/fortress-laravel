<?php

namespace App\Domain\Financeiro\Services;

use App\Domain\Financeiro\DataTransferObjects\AllocationData;
use App\Domain\Financeiro\DataTransferObjects\InstallmentData;
use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Models\JournalEntry;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection as SupportCollection;

class SyncInstallmentClonesService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CreateJournalEntryService $createJournalEntry,
        private readonly UpdateJournalEntryService $updateJournalEntry,
        private readonly JournalEntryStateService $stateService,
    ) {
    }

    public function handle(JournalEntry $entry): void
    {
        if ($entry->clone_of_id) {
            return;
        }

        $this->database->transaction(function () use ($entry) {
            $entry->refresh()->load([
                'installments' => fn ($query) => $query->orderBy('numero_parcela'),
                'allocations',
            ]);

            $installments = $entry->installments;

            if ($installments->isEmpty()) {
                $this->cleanupClones($entry, []);
                $this->resetInstallmentMeta($installments);
                $this->syncEntryOrigin($entry, false);
                $this->stateService->sync($entry->refresh(), JournalEntryStatus::from($entry->status));

                return;
            }

            if ($installments->count() <= 1) {
                $this->cleanupClones($entry, []);
                $this->resetInstallmentMeta($installments);
                $this->syncEntryOrigin($entry, false);
                $this->stateService->sync($entry->refresh(), JournalEntryStatus::from($entry->status));

                return;
            }

            $this->syncEntryOrigin($entry, true);

            $allocationsData = $entry->allocations->map(fn ($allocation) => new AllocationData(
                costCenterId: $allocation->cost_center_id,
                propertyId: $allocation->property_id,
                percentage: $allocation->percentage !== null ? (float) $allocation->percentage : null,
                amount: $allocation->amount !== null ? (float) $allocation->amount : null,
            ));

            $typeEnum = JournalEntryType::from($entry->type);
            $totalInstallments = $installments->count();
            $activeCloneIds = [];

            foreach ($installments as $installment) {
                $meta = is_array($installment->meta) ? $installment->meta : [];
                $linkedCloneId = isset($meta['linked_journal_entry_id']) ? (int) $meta['linked_journal_entry_id'] : null;
                $parcelLabel = sprintf('Parcela %d/%d', $installment->numero_parcela, $totalInstallments);
                $notes = $this->composeNotes($entry->notes, $parcelLabel);
                $statusEnum = JournalEntryStatus::from($installment->status ?? 'planejado');

                $cloneDto = new JournalEntryData(
                    type: $typeEnum,
                    bankAccountId: (int) $entry->bank_account_id,
                    counterBankAccountId: $entry->counter_bank_account_id,
                    costCenterId: $entry->cost_center_id,
                    propertyId: $entry->property_id,
                    personId: $entry->person_id,
                    descriptionId: $entry->description_id,
                    descriptionCustom: $entry->description_custom,
                    notes: $notes,
                    referenceCode: $entry->reference_code,
                    origin: 'parcelado',
                    cloneOfId: $entry->id,
                    movementDate: optional($installment->movement_date)->toDateString() ?? optional($entry->movement_date)->toDateString(),
                    dueDate: optional($installment->due_date)->toDateString(),
                    paymentDate: optional($installment->payment_date)->toDateString(),
                    currency: $entry->currency ?? 'BRL',
                    status: $statusEnum,
                    amount: (float) $installment->valor_total,
                    installments: SupportCollection::make([
                        new InstallmentData(
                            numeroParcela: 1,
                            movementDate: optional($installment->movement_date)->toDateString() ?? optional($entry->movement_date)->toDateString(),
                            dueDate: optional($installment->due_date)->toDateString() ?? optional($installment->movement_date)->toDateString(),
                            paymentDate: optional($installment->payment_date)->toDateString(),
                            valorPrincipal: (float) $installment->valor_principal,
                            valorJuros: (float) $installment->valor_juros,
                            valorMulta: (float) $installment->valor_multa,
                            valorDesconto: (float) $installment->valor_desconto,
                            valorTotal: (float) $installment->valor_total,
                            status: $statusEnum,
                            meta: [
                                'source_installment_id' => $installment->id,
                                'source_parent_id' => $entry->id,
                                'parcel_number' => $installment->numero_parcela,
                                'parcel_total' => $totalInstallments,
                                'parcel_label' => $parcelLabel,
                            ],
                        ),
                    ]),
                    allocations: $allocationsData,
                    createdBy: $entry->created_by,
                    updatedBy: $entry->updated_by,
                );

                if ($linkedCloneId) {
                    $clone = JournalEntry::query()->with(['installments'])->find($linkedCloneId);

                    if ($clone && $clone->clone_of_id === $entry->id) {
                        $clone = $this->updateJournalEntry->handle($clone, $cloneDto);
                        $activeCloneIds[] = $clone->id;
                        $meta = $this->upsertInstallmentMeta($meta, $clone->id, $parcelLabel, $totalInstallments);
                        $installment->meta = $meta;
                        $installment->save();

                        continue;
                    }
                }

                $clone = $this->createJournalEntry->handle($cloneDto);
                $activeCloneIds[] = $clone->id;

                $meta = $this->upsertInstallmentMeta($meta, $clone->id, $parcelLabel, $totalInstallments);
                $installment->meta = $meta;
                $installment->save();
            }

            $this->cleanupClones($entry, $activeCloneIds);
            $this->stateService->sync($entry->refresh(), JournalEntryStatus::from($entry->status));
        });
    }

    private function cleanupClones(JournalEntry $entry, array $activeCloneIds): void
    {
        $query = JournalEntry::query()->where('clone_of_id', $entry->id);

        if (! empty($activeCloneIds)) {
            $query->whereNotIn('id', $activeCloneIds);
        }

        $query->each(function (JournalEntry $clone) {
            $clone->delete();
        });
    }

    private function composeNotes(?string $originalNotes, string $parcelLabel): string
    {
        $lines = SupportCollection::make(preg_split("/\r\n|\r|\n/", (string) $originalNotes) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '' && !preg_match('/^Parcela\s+\d+\/\d+/i', $line));

        $lines->push($parcelLabel);

        return trim($lines->implode(PHP_EOL));
    }

    private function upsertInstallmentMeta(array $meta, int $cloneId, string $label, int $total): array
    {
        $meta['linked_journal_entry_id'] = $cloneId;
        $meta['parcel_label'] = $label;
        $meta['parcel_total'] = $total;

        return $meta;
    }

    private function resetInstallmentMeta(SupportCollection $installments): void
    {
        $installments->each(function ($installment) {
            if (! $installment) {
                return;
            }

            $meta = is_array($installment->meta) ? $installment->meta : [];

            unset(
                $meta['linked_journal_entry_id'],
                $meta['parcel_label'],
                $meta['parcel_total']
            );

            $installment->meta = empty($meta) ? null : $meta;
            $installment->save();
        });
    }

    private function syncEntryOrigin(JournalEntry $entry, bool $hasMultipleInstallments): void
    {
        $targetOrigin = $hasMultipleInstallments ? 'parcelado' : 'manual';

        if ($entry->origin !== $targetOrigin) {
            $entry->origin = $targetOrigin;
            $entry->save();
        }
    }
}
