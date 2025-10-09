<?php

namespace App\Http\Requests\Imovel;

use Illuminate\Foundation\Http\FormRequest;

class ImovelStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('imoveis.create');
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:50', 'unique:imoveis,codigo'],
            'proprietario_id' => ['required', 'integer', 'exists:pessoas,id'],
            'agenciador_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'responsavel_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'tipo_imovel' => ['required', 'string', 'max:120'],
            'finalidade' => ['required', 'array', 'min:1'],
            'finalidade.*' => ['string', 'in:Locacao,Venda'],
            'disponibilidade' => ['required', 'in:Disponivel,Indisponivel'],
            'cep' => ['nullable', 'string', 'max:20'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'rua' => ['nullable', 'string', 'max:150'],
            'condominio_id' => ['nullable', 'integer', 'exists:condominios,id'],
            'logradouro' => ['nullable', 'string', 'max:150'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:150'],
            'valor_locacao' => ['nullable', 'numeric', 'min:0'],
            'valor_condominio' => ['nullable', 'numeric', 'min:0'],
            'condominio_isento' => ['boolean'],
            'valor_iptu' => ['nullable', 'numeric', 'min:0'],
            'iptu_isento' => ['boolean'],
            'outros_valores' => ['nullable', 'numeric', 'min:0'],
            'outros_isento' => ['boolean'],
            'periodo_iptu' => ['required', 'in:Mensal,Anual'],
            'dormitorios' => ['nullable', 'integer', 'between:0,255'],
            'suites' => ['nullable', 'integer', 'between:0,255'],
            'banheiros' => ['nullable', 'integer', 'between:0,255'],
            'vagas_garagem' => ['nullable', 'integer', 'between:0,255'],
            'area_total' => ['nullable', 'numeric', 'min:0'],
            'area_construida' => ['nullable', 'numeric', 'min:0'],
            'comodidades' => ['nullable', 'array'],
            'comodidades.*' => ['string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $moneyFields = [
            'valor_locacao',
            'valor_condominio',
            'valor_iptu',
            'outros_valores',
            'area_total',
            'area_construida',
        ];

        $integerFields = ['dormitorios', 'suites', 'banheiros', 'vagas_garagem'];
        $booleanFields = ['condominio_isento', 'iptu_isento', 'outros_isento'];

        $data = $this->all();

        foreach ($moneyFields as $field) {
            $data[$field] = $this->normalizeDecimal($this->input($field));
        }

        foreach ($integerFields as $field) {
            $value = $this->input($field);
            $data[$field] = $value === null || $value === '' ? null : (int) $value;
        }

        foreach ($booleanFields as $field) {
            $data[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN);
        }

        $data['finalidade'] = $this->normalizeArray($this->input('finalidade'), ['Locacao', 'Venda']);
        $data['comodidades'] = $this->normalizeArray($this->input('comodidades'));

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

    private function normalizeArray(mixed $value, array $allowed = []): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        $value = array_filter(is_array($value) ? $value : [], fn ($item) => $item !== null && $item !== '');
        $value = array_map(function ($item) {
            $item = is_string($item) ? trim($item) : $item;
            $item = is_string($item) ? ucfirst(mb_strtolower($item)) : $item;
            return $item;
        }, $value);

        if ($allowed !== []) {
            $value = array_values(array_intersect($value, $allowed));
        } else {
            $value = array_values(array_unique($value));
        }

        return $value;
    }
}