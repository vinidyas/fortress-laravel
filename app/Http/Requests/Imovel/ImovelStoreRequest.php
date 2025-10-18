<?php

namespace App\Http\Requests\Imovel;

use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;

class ImovelStoreRequest extends FormRequest
{
    use NormalizesDecimalValues;

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
            'anexos' => ['nullable', 'array'],
            'anexos.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'anexos_legendas' => ['nullable', 'array'],
            'anexos_legendas.*' => ['nullable', 'string', 'max:255'],
            'anexos_legendas_existentes' => ['nullable', 'array'],
            'anexos_legendas_existentes.*' => ['nullable', 'string', 'max:255'],
            'anexos_remover' => ['nullable', 'array'],
            'anexos_remover.*' => ['integer'],
            'fotos' => ['nullable', 'array'],
            'fotos.*' => ['image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'fotos_legendas' => ['nullable', 'array'],
            'fotos_legendas.*' => ['nullable', 'string', 'max:255'],
            'fotos_legendas_existentes' => ['nullable', 'array'],
            'fotos_legendas_existentes.*' => ['nullable', 'string', 'max:255'],
            'fotos_remover' => ['nullable', 'array'],
            'fotos_remover.*' => ['integer'],
            'fotos_ordem' => ['nullable', 'array'],
            'fotos_ordem.*' => ['string', 'regex:/^(existing|new):[A-Za-z0-9_-]+$/'],
            'fotos_ids' => ['nullable', 'array'],
            'fotos_ids.*' => ['string', 'max:120'],
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
            $data[$field] = $this->normalizeDecimalToNullableString($this->input($field));
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

        $data['anexos_legendas'] = $this->normalizeStringArray($this->input('anexos_legendas'));
        $data['anexos_legendas_existentes'] = $this->normalizeStringAssociativeArray($this->input('anexos_legendas_existentes'));
        $data['anexos_remover'] = $this->normalizeIntegerArray($this->input('anexos_remover'));
        $data['fotos_legendas'] = $this->normalizeStringArray($this->input('fotos_legendas'));
        $data['fotos_legendas_existentes'] = $this->normalizeStringAssociativeArray($this->input('fotos_legendas_existentes'));
        $data['fotos_remover'] = $this->normalizeIntegerArray($this->input('fotos_remover'));
        $data['fotos_ordem'] = $this->normalizeStringArray($this->input('fotos_ordem'));
        $data['fotos_ids'] = $this->normalizeStringArray($this->input('fotos_ids'));

        $this->merge($data);
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

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(function ($item) {
            $item = is_string($item) ? trim($item) : '';

            return mb_substr($item, 0, 255);
        }, $value));
    }

    /**
     * @param  mixed  $value
     * @return array<int|string, string>
     */
    private function normalizeStringAssociativeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = mb_substr(is_string($item) ? trim($item) : '', 0, 255);
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @return array<int, int>
     */
    private function normalizeIntegerArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(fn ($item) => is_numeric($item) ? (int) $item : null, $value),
                fn ($item) => $item !== null
            )
        );
    }
}
