<?php

namespace App\Http\Requests\Contrato;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContratoStoreRequest extends FormRequest
{
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
            'fiador_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'dia_vencimento' => ['required', 'integer', 'between:1,28'],
            'valor_aluguel' => ['required', 'numeric', 'min:0'],
            'reajuste_indice' => ['nullable', 'string', 'max:20'],
            'data_proximo_reajuste' => ['nullable', 'date'],
            'garantia_tipo' => ['nullable', Rule::in(['Fiador', 'Seguro', 'Caucao', 'SemGarantia'])],
            'caucao_valor' => ['nullable', 'numeric', 'min:0'],
            'taxa_adm_percentual' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['Ativo', 'Suspenso', 'Encerrado'])],
            'observacoes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $decimalFields = ['valor_aluguel', 'caucao_valor', 'taxa_adm_percentual'];

        $data = $this->all();

        foreach ($decimalFields as $field) {
            $data[$field] = $this->normalizeDecimal($this->input($field));
        }

        if (empty($data['reajuste_indice'])) {
            $data['reajuste_indice'] = 'IGPM';
        }

        if (empty($data['status'])) {
            $data['status'] = 'Ativo';
        }

        $this->merge($data);
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $value = preg_replace('/[^0-9,.-]/', '', (string) $value);
        $value = str_replace(['. ', ' '], '', $value);
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return $value === '' ? null : $value;
    }
}
