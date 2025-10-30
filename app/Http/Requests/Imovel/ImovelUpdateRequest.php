<?php

namespace App\Http\Requests\Imovel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ImovelUpdateRequest extends ImovelStoreRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('imoveis.update');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        /** @var mixed $routeValue */
        $routeValue = $this->route('imovel')
            ?? $this->route('imoveis')
            ?? $this->route('imovei');

        $ignoreId = $routeValue instanceof Model
            ? $routeValue->getKey()
            : $routeValue;

        $imovelKey = is_numeric($ignoreId) ? (int) $ignoreId : $ignoreId;

        $rules['codigo'] = [
            'nullable',
            'string',
            'max:50',
            Rule::unique('imoveis', 'codigo')->ignore($imovelKey),
        ];

        $rules['anexos_remover.*'] = [
            'integer',
            Rule::exists('imovel_anexos', 'id')->where('imovel_id', $imovelKey),
        ];

        $rules['fotos_remover.*'] = [
            'integer',
            Rule::exists('imovel_fotos', 'id')->where('imovel_id', $imovelKey),
        ];

        return $rules;
    }
}
