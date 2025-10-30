<?php

namespace App\Http\Requests\Financeiro;

use App\Models\FinancialTransaction;
use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;

class FinancialTransactionReconcileRequest extends FormRequest
{
    use NormalizesDecimalValues;

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
            'valor_conciliado' => $this->normalizeDecimalToFloat($this->input('valor_conciliado')),
        ]);
    }
}
