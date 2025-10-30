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
            'apelido' => ['nullable', 'string', 'max:60'],
            'nome' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(['conta_corrente', 'poupanca', 'investimento', 'caixa', 'outro'])],
            'instituicao' => ['nullable', 'string', 'max:120'],
            'banco' => ['nullable', 'string', 'max:120'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero' => ['nullable', 'string', 'max:40'],
            'carteira' => ['nullable', 'string', 'max:20'],
            'moeda' => ['nullable', 'string', 'size:3'],
            'saldo_inicial' => ['required', 'numeric', 'min:0'],
            'data_saldo_inicial' => ['nullable', 'date'],
            'categoria' => ['nullable', Rule::in(['operacional', 'reserva', 'investimento'])],
            'permite_transf' => ['sometimes', 'boolean'],
            'padrao_recebimento' => ['sometimes', 'boolean'],
            'padrao_pagamento' => ['sometimes', 'boolean'],
            'observacoes' => ['nullable', 'string'],
            'ativo' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'saldo_inicial' => $this->normalizeDecimalToFloat($this->input('saldo_inicial', 0), null),
        ];

        foreach (['permite_transf', 'padrao_recebimento', 'padrao_pagamento'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = $this->boolean($field);
            }
        }

        $this->merge($payload);
    }
}
