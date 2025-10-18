<?php

declare(strict_types=1);

namespace App\Http\Requests\Contrato;

use App\Enums\ContratoFormaPagamento;
use App\Enums\ContratoGarantiaTipo;
use App\Enums\ContratoReajusteIndice;
use App\Enums\ContratoStatus;
use App\Enums\ContratoTipo;
use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContratoStoreRequest extends FormRequest
{
    use NormalizesDecimalValues;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('contratos.create');
    }

    public function rules(): array
    {
        return [
            'codigo_contrato' => ['required', 'string', 'max:30', 'unique:contratos,codigo_contrato'],
            'imovel_id' => ['required', 'integer', 'exists:imoveis,id'],
            'locador_id' => ['required', 'integer', 'exists:pessoas,id'],
            'locatario_id' => ['required', 'integer', 'exists:pessoas,id'],
            'fiadores' => ['nullable', 'array'],
            'fiadores.*' => ['integer', 'distinct', 'exists:pessoas,id'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'dia_vencimento' => ['required', 'integer', 'between:1,28'],
            'carencia_meses' => ['nullable', 'integer', 'min:0', 'max:120'],
            'data_entrega_chaves' => ['nullable', 'date'],
            'valor_aluguel' => ['required', 'numeric', 'min:0'],
            'reajuste_indice' => ['required', Rule::in(ContratoReajusteIndice::values())],
            'reajuste_indice_outro' => [
                'nullable',
                'string',
                'max:60',
                Rule::requiredIf(fn () => $this->input('reajuste_indice') === ContratoReajusteIndice::Outro->value),
            ],
            'reajuste_periodicidade_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'data_proximo_reajuste' => ['nullable', 'date'],
            'reajuste_teto_percentual' => ['nullable', 'numeric', 'min:0'],
            'garantia_tipo' => ['required', Rule::in(ContratoGarantiaTipo::values())],
            'caucao_valor' => ['nullable', 'numeric', 'min:0', 'required_if:garantia_tipo,' . ContratoGarantiaTipo::Caucao->value],
            'multa_atraso_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'juros_mora_percentual_mes' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'multa_rescisao_alugueis' => ['required', 'numeric', 'min:0'],
            'repasse_automatico' => ['nullable', 'boolean'],
            'conta_cobranca_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'forma_pagamento_preferida' => ['nullable', Rule::in(ContratoFormaPagamento::values())],
            'tipo_contrato' => ['nullable', Rule::in(ContratoTipo::values())],
            'status' => ['nullable', Rule::in(ContratoStatus::values())],
            'observacoes' => ['nullable', 'string'],
            'anexos' => ['nullable', 'array'],
            'anexos.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $decimalFields = [
            'valor_aluguel',
            'caucao_valor',
            'multa_atraso_percentual',
            'juros_mora_percentual_mes',
            'multa_rescisao_alugueis',
            'reajuste_teto_percentual',
        ];

        $data = $this->all();

        foreach ($decimalFields as $field) {
            $data[$field] = $this->normalizeDecimalToNullableString($this->input($field));
        }

        if (! isset($data['reajuste_indice']) || $data['reajuste_indice'] === '') {
            $data['reajuste_indice'] = ContratoReajusteIndice::IGPM->value;
        }

        if ($this->isMethod('post') && (! isset($data['status']) || $data['status'] === '')) {
            $data['status'] = ContratoStatus::Ativo->value;
        }

        if ($this->has('repasse_automatico')) {
            $data['repasse_automatico'] = $this->boolean('repasse_automatico');
        } elseif ($this->isMethod('post')) {
            $data['repasse_automatico'] = false;
        }

        foreach (['forma_pagamento_preferida', 'tipo_contrato'] as $enumField) {
            if (array_key_exists($enumField, $data) && $data[$enumField] === '') {
                $data[$enumField] = null;
            }
        }

        if (($data['reajuste_indice'] ?? null) !== ContratoReajusteIndice::Outro->value) {
            $data['reajuste_indice_outro'] = null;
        }

        if (($data['reajuste_indice'] ?? null) === ContratoReajusteIndice::SemReajuste->value) {
            $data['reajuste_teto_percentual'] = null;
        }

        $this->merge($data);
    }
}
