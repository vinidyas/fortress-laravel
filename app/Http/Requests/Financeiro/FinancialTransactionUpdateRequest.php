<?php

namespace App\Http\Requests\Financeiro;

use App\Models\FinancialTransaction;
use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialTransactionUpdateRequest extends FormRequest
{
    use NormalizesDecimalValues;

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
                'valor' => $this->normalizeDecimalToFloat($this->input('valor')),
            ]);
        }
    }
}
