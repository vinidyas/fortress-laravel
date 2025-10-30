<?php

namespace App\Http\Requests\Financeiro;

use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialAccountBalanceRequest extends FormRequest
{
    use NormalizesDecimalValues;

    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account && $this->user()?->can('update', $account);
    }

    public function rules(): array
    {
        return [
            'saldo_inicial' => ['required', 'numeric'],
            'data_saldo_inicial' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'saldo_inicial' => $this->normalizeDecimalToFloat($this->input('saldo_inicial', 0), null),
        ]);
    }
}
