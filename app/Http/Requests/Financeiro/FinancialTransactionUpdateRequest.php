<?php

namespace App\Http\Requests\Financeiro;

use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialTransactionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');
        $user = $this->user();

        if (! $user || ! $transaction instanceof FinancialTransaction) {
            return false;
        }

        return $user->can('update', $transaction);
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer', 'exists:financial_accounts,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'contrato_id' => ['nullable', 'integer', 'exists:contratos,id'],
            'fatura_id' => ['nullable', 'integer', 'exists:faturas,id'],
            'tipo' => ['sometimes', Rule::in(['credito', 'debito'])],
            'valor' => ['sometimes', 'numeric', 'min:0.01'],
            'data_ocorrencia' => ['sometimes', 'date'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('valor')) {
            $this->merge([
                'valor' => $this->normalizeDecimal($this->input('valor')), 
            ]);
        }
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