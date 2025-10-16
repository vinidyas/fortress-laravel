<?php

namespace App\Http\Requests\Condominio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CondominioUpdateRequest extends CondominioStoreRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('condominios.update');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        /** @var mixed $routeValue */
        $routeValue = $this->route('condominio')
            ?? $this->route('condominios');

        $ignoreId = $routeValue instanceof Model
            ? $routeValue->getKey()
            : $routeValue;

        $rules['cnpj'] = [
            'nullable',
            'string',
            'max:20',
            Rule::unique('condominios', 'cnpj')->ignore($ignoreId),
        ];

        return $rules;
    }
}

