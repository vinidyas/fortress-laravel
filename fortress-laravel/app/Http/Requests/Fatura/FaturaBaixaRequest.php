<?php

namespace App\Http\Requests\Fatura;

use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FaturaBaixaRequest extends FormRequest
{
    use NormalizesDecimalValues;

    private const METODOS = ['PIX', 'Boleto', 'Transferencia', 'Dinheiro', 'Cartao', 'Outro'];

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('faturas.settle');
    }

    public function rules(): array
    {
        return [
            'valor_pago' => ['required', 'numeric', 'min:0'],
            'pago_em' => ['required', 'date'],
            'metodo_pagamento' => ['required', Rule::in(self::METODOS)],
            'observacoes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        $data['valor_pago'] = $this->normalizeDecimalToNullableString($this->input('valor_pago'));
        $data['pago_em'] = $this->normalizeDate($this->input('pago_em'));

        $this->merge($data);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
