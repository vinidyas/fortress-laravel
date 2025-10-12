<?php

namespace App\Http\Requests\Financeiro;

use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialAccountRequest extends FormRequest
{
    use NormalizesDecimalValues;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $account = $this->route('account');

        if ($this->isMethod('post')) {
            return $user->hasPermission('financeiro.create');
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return $user->can('update', $account);
        }

        if ($this->isMethod('delete')) {
            return $user->can('delete', $account);
        }

        return $user->hasPermission('financeiro.view');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(['conta_corrente', 'caixa', 'outro'])],
            'banco' => ['nullable', 'string', 'max:120'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero' => ['nullable', 'string', 'max:40'],
            'saldo_inicial' => ['required', 'numeric', 'min:0'],
            'ativo' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'saldo_inicial' => $this->normalizeDecimalToFloat($this->input('saldo_inicial', 0), null),
        ]);
    }
}
