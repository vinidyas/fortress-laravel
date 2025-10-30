<?php

namespace App\Http\Resources\Financeiro;

use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = $this->status ? (string) $this->status : null;
        $statusEnum = $statusValue ? JournalEntryStatus::tryFrom($statusValue) : null;
        $typeEnum = $this->type ? JournalEntryType::tryFrom((string) $this->type) : null;
        $statusLabel = $statusEnum
            ? $statusEnum->label($typeEnum)
            : ($statusValue ? ucfirst($statusValue) : null);
        $statusCategory = $statusEnum?->category();

        $propertyLabel = null;
        if ($this->relationLoaded('property') && $this->property) {
            $propertyLabel = $this->resolvePropertyLabel($this->property);
        }

        $propertyMccLabel = $propertyLabel;
        $canQueryInstallments = Schema::hasTable('journal_entry_installments');

        if (! $propertyMccLabel) {
            $propertyMccLabel = $this->costCenter?->nome;

            if (! $propertyMccLabel) {
                if ($this->relationLoaded('installments')) {
                    $firstInstallment = $this->installments->first();
                    if ($firstInstallment && is_array($firstInstallment->meta ?? null)) {
                        $propertyMccLabel = $firstInstallment->meta['property_label'] ?? null;
                    }
                } elseif ($canQueryInstallments) {
                    $firstInstallment = $this->installments()
                        ->select('meta')
                        ->orderBy('id')
                        ->first();
                    if ($firstInstallment && is_array($firstInstallment->meta ?? null)) {
                        $propertyMccLabel = $firstInstallment->meta['property_label'] ?? null;
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'tipo' => $this->type,
            'amount' => $this->amount,
            'valor' => $this->amount,
            'valor_formatado' => number_format((float) $this->amount, 2, ',', '.'),
            'movement_date' => $this->movement_date?->toDateString(),
            'data_ocorrencia' => $this->movement_date?->toDateString(),
            'data_ocorrencia_formatada' => $this->movement_date?->format('d/m/Y'),
            'due_date' => $this->due_date?->toDateString(),
            'payment_date' => $this->payment_date?->toDateString(),
            'status' => $this->status,
            'status_code' => $this->status,
            'status_label' => $statusLabel,
            'status_category' => $statusCategory,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'description' => $this->description_custom,
            'description_id' => $this->description_id,
            'reference_code' => $this->reference_code,
            'origin' => $this->origin,
            'clone_of_id' => $this->clone_of_id,
            'account' => $this->whenLoaded('bankAccount', fn () => [
                'id' => $this->bankAccount->id,
                'nome' => $this->bankAccount->nome,
            ]),
            'counter_account' => $this->whenLoaded('counterBankAccount', fn () => [
                'id' => $this->counterBankAccount->id,
                'nome' => $this->counterBankAccount->nome,
            ]),
            'cost_center' => $this->whenLoaded('costCenter', fn () => [
                'id' => $this->costCenter->id,
                'nome' => $this->costCenter->nome,
                'codigo' => $this->costCenter->codigo,
                'tipo' => $this->costCenter->tipo,
                'parent' => $this->costCenter->parent
                    ? [
                        'id' => $this->costCenter->parent->id,
                        'nome' => $this->costCenter->parent->nome,
                        'codigo' => $this->costCenter->parent->codigo,
                        'tipo' => $this->costCenter->parent->tipo,
                    ]
                    : null,
            ]),
            'property' => $this->whenLoaded('property', fn () => [
                'id' => $this->property->id,
                'nome' => $propertyLabel,
            ]),
            'property_label' => $propertyLabel,
            'propertyLabel' => $propertyLabel,
            'property_label_mcc' => $propertyMccLabel,
            'propertyLabelMcc' => $propertyMccLabel,
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'nome' => $this->person->nome,
            ]),
            'installments' => $this->whenLoaded('installments', fn () => $this->installments->map(function ($installment) use ($typeEnum) {
                $installmentStatusValue = $installment->status ? (string) $installment->status : null;
                $installmentEnum = $installmentStatusValue ? JournalEntryStatus::tryFrom($installmentStatusValue) : null;

                return [
                    'id' => $installment->id,
                    'numero_parcela' => $installment->numero_parcela,
                    'movement_date' => $installment->movement_date?->toDateString(),
                    'due_date' => $installment->due_date?->toDateString(),
                    'payment_date' => $installment->payment_date?->toDateString(),
                    'valor_principal' => $installment->valor_principal,
                    'valor_juros' => $installment->valor_juros,
                    'valor_multa' => $installment->valor_multa,
                    'valor_desconto' => $installment->valor_desconto,
                    'valor_total' => $installment->valor_total,
                    'status' => $installment->status,
                    'status_label' => $installmentEnum
                        ? $installmentEnum->label($typeEnum)
                        : ($installmentStatusValue ? ucfirst($installmentStatusValue) : null),
                    'status_category' => $installmentEnum?->category(),
                    'meta' => $installment->meta,
                ];
            })),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'cost_center_id' => $allocation->cost_center_id,
                'property_id' => $allocation->property_id,
                'percentage' => $allocation->percentage,
                'amount' => $allocation->amount,
            ])),
            'attachments' => $this->whenLoaded('attachments', fn () => JournalEntryAttachmentResource::collection($this->attachments)),
            'receipts' => $this->whenLoaded('receipts', fn () => FinancialReceiptResource::collection($this->receipts)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolvePropertyLabel($property): ?string
    {
        $segments = [];

        if (! empty($property->complemento)) {
            $segments[] = trim((string) $property->complemento);
        }

        if (! empty($property->logradouro)) {
            $logradouro = trim((string) $property->logradouro);
            if (! empty($property->numero)) {
                $logradouro = trim($logradouro.' '.$property->numero);
            }
            $segments[] = $logradouro;
        }

        if (! empty($property->bairro)) {
            $segments[] = trim((string) $property->bairro);
        }

        if (! empty($property->cidade)) {
            $segments[] = trim((string) $property->cidade);
        }

        if (empty($segments) && ! empty($property->codigo)) {
            $segments[] = trim((string) $property->codigo);
        }

        $label = trim(implode(' • ', array_filter($segments)));

        return $label !== '' ? $label : ($property->codigo ?? null);
    }
}
