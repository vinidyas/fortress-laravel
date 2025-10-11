<?php

namespace App\Http\Requests\Financeiro;

use App\Models\CostCenter;
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
        $resource = $this->route('cost_center');
        $resourceId = $resource instanceof CostCenter ? $resource->id : $resource;

        return [
            'nome' => [
                'required',
                'string',
                'max:150',
                Rule::unique('cost_centers', 'nome')->ignore($resourceId),
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
            'codigo' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\d+(\.\d+)?$/',
                Rule::unique('cost_centers', 'codigo')->ignore($resourceId),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => $this->filled('codigo') ? trim((string) $this->input('codigo')) : null,
            'descricao' => $this->filled('descricao') ? trim((string) $this->input('descricao')) : null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $parentId = $this->input('parent_id');
            if (! $parentId) {
                return;
            }

            $parent = CostCenter::query()->find($parentId);
            if (! $parent) {
                return;
            }

            if ($parent->parent_id) {
                $validator->errors()->add('parent_id', 'Somente centros principais podem ser selecionados como pai.');
            }

            $current = $this->route('cost_center');
            if ($current instanceof CostCenter && $current->id === (int) $parentId) {
                $validator->errors()->add('parent_id', 'Um centro de custo nao pode ser pai de si mesmo.');
            }
        });
    }
}
