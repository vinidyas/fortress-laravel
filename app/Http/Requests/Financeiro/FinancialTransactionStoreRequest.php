<?php

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialTransactionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->hasPermission('financeiro.create');
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'contrato_id' => ['nullable', 'integer', 'exists:contratos,id'],
            'fatura_id' => ['nullable', 'integer', 'exists:faturas,id'],
            'tipo' => ['required', Rule::in(['credito', 'debito'])],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_ocorrencia' => ['required', 'date'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pendente', 'conciliado', 'cancelado'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $valor = $this->normalizeDecimal($this->input('valor'));
        $status = $this->input('status');

        if (! $status) {
            $status = 'pendente';
        }

        $this->merge([
            'valor' => $valor,
            'status' => $status,
        ]);
    }

    private function normalizeDecimal(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $value = preg_replace('/[^0-9,.-]/', '', (string) $value);
        $value = str_replace(['. ', ' '], '', $value);
        $value = str_replace('.', '', $value);

        return round((float) str_replace(',', '.', $value), 2);
    }
}