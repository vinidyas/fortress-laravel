<?php

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $resource = $this->route('cost_center');

        if ($this->isMethod('post')) {
            return $user->hasPermission('financeiro.create');
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return $user->can('update', $resource);
        }

        if ($this->isMethod('delete')) {
            return $user->can('delete', $resource);
        }

        return $user->hasPermission('financeiro.view');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120', Rule::unique('cost_centers', 'nome')->ignore($this->route('cost_center'))],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }
}