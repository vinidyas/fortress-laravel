<?php

namespace App\Http\Requests\Condominio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CondominioStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('condominios.create');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'cnpj' => ['nullable', 'string', 'max:20', Rule::unique('condominios', 'cnpj')],
            'cep' => ['nullable', 'string', 'max:20'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'rua' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'max:150', 'email'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}

