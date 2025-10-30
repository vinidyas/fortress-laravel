<?php

namespace App\Http\Requests\Pessoa;

use Illuminate\Validation\Rule;

class PessoaUpdateRequest extends PessoaStoreRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('pessoas.update');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $id = $this->route('pessoas') ?? $this->route('pessoa');

        if (isset($rules['cpf_cnpj']) && is_array($rules['cpf_cnpj'])) {
            $rules['cpf_cnpj'] = collect($rules['cpf_cnpj'])
                ->reject(fn ($rule) => $rule instanceof \Illuminate\Validation\Rules\Unique)
                ->push(Rule::unique('pessoas', 'cpf_cnpj')->ignore($id))
                ->all();
        }

        return $rules;
    }
}
