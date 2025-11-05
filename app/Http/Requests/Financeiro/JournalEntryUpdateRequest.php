<?php

namespace App\Http\Requests\Financeiro;

use App\Domain\Financeiro\Support\JournalEntryType;
use App\Models\JournalEntry;
use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryUpdateRequest extends FormRequest
{
    use NormalizesDecimalValues;

    public function authorize(): bool
    {
        $entry = $this->route('journal_entry');

        return $entry instanceof JournalEntry && $this->user()?->can('update', $entry);
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(array_column(JournalEntryType::cases(), 'value'))],
            'bank_account_id' => ['sometimes', 'integer', 'exists:financial_accounts,id'],
            'counter_bank_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'property_id' => ['nullable', 'integer', 'exists:imoveis,id'],
            'person_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'description_id' => ['nullable', 'integer', 'exists:journal_entry_descriptions,id'],
            'description_custom' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reference_code' => ['nullable', 'string', 'max:40'],
            'improvement_type' => ['nullable', Rule::in(['reforma', 'investimento'])],
            'movement_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['planejado', 'pendente', 'pago', 'cancelado', 'atrasado'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'installments' => ['sometimes', 'array', 'min:1'],
            'installments.*.numero_parcela' => ['nullable', 'integer', 'min:1'],
            'installments.*.movement_date' => ['required_with:installments', 'date'],
            'installments.*.due_date' => ['required_with:installments', 'date'],
            'installments.*.payment_date' => ['nullable', 'date'],
            'installments.*.valor_principal' => ['required_with:installments', 'numeric', 'min:0'],
            'installments.*.valor_juros' => ['nullable', 'numeric', 'min:0'],
            'installments.*.valor_multa' => ['nullable', 'numeric', 'min:0'],
            'installments.*.valor_desconto' => ['nullable', 'numeric', 'min:0'],
            'installments.*.valor_total' => ['required_with:installments', 'numeric', 'min:0.01'],
            'installments.*.status' => ['nullable', Rule::in(['planejado', 'pendente', 'pago', 'cancelado', 'atrasado'])],
            'installments.*.meta' => ['nullable', 'array'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.cost_center_id' => ['required', 'integer', 'exists:cost_centers,id'],
            'allocations.*.property_id' => ['nullable', 'integer', 'exists:imoveis,id'],
            'allocations.*.percentage' => ['nullable', 'numeric', 'between:0,100'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount')) {
            $this->merge([
                'amount' => $this->normalizeDecimalToFloat($this->input('amount')),
            ]);
        }

        if (is_array($this->input('installments'))) {
            $installments = collect($this->input('installments'))->map(function ($item) {
                return array_merge($item, [
                    'valor_principal' => $this->normalizeDecimalToFloat($item['valor_principal'] ?? 0),
                    'valor_juros' => $this->normalizeDecimalToFloat($item['valor_juros'] ?? 0),
                    'valor_multa' => $this->normalizeDecimalToFloat($item['valor_multa'] ?? 0),
                    'valor_desconto' => $this->normalizeDecimalToFloat($item['valor_desconto'] ?? 0),
                    'valor_total' => $this->normalizeDecimalToFloat($item['valor_total'] ?? 0),
                ]);
            })->toArray();

            $this->merge(['installments' => $installments]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') === JournalEntryType::Transferencia->value && ! $this->filled('counter_bank_account_id')) {
                $validator->errors()->add('counter_bank_account_id', 'Transferências devem informar a conta destino.');
            }
        });
    }
}
