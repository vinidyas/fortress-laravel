<?php

declare(strict_types=1);

namespace App\Http\Requests\Contrato;

use Illuminate\Validation\Rule;

class ContratoUpdateRequest extends ContratoStoreRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('contratos.update');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $id = $this->route('contratos') ?? $this->route('contrato');

        $rules['codigo_contrato'] = [
            'required',
            'string',
            'max:30',
            Rule::unique('contratos', 'codigo_contrato')->ignore($id),
        ];

        $rules['anexos_remover'] = ['nullable', 'array'];
        $rules['anexos_remover.*'] = ['integer', Rule::exists('contrato_anexos', 'id')->where('contrato_id', $id)];

        return $rules;
    }
}
