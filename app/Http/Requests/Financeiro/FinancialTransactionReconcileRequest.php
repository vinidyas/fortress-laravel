<?php

namespace App\Http\Requests\Financeiro;

use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;

class FinancialTransactionReconcileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');
        $user = $this->user();

        if (! $user || ! $transaction instanceof FinancialTransaction) {
            return false;
        }

        return $user->can('reconcile', $transaction);
    }

    public function rules(): array
    {
        return [
            'valor_conciliado' => ['required', 'numeric', 'min:0.01'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'valor_conciliado' => $this->normalizeDecimal($this->input('valor_conciliado')),
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