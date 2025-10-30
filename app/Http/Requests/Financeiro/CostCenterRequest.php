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
            'tipo' => ['required', Rule::in(['fixo', 'variavel', 'investimento'])],
            'ativo' => ['sometimes', 'boolean'],
            'orcamento_anual' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'codigo' => $this->filled('codigo') ? trim((string) $this->input('codigo')) : null,
            'descricao' => $this->filled('descricao') ? trim((string) $this->input('descricao')) : null,
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
        ];

        if ($this->has('ativo')) {
            $payload['ativo'] = $this->boolean('ativo');
        }

        if ($this->filled('orcamento_anual')) {
            $payload['orcamento_anual'] = (float) $this->input('orcamento_anual');
        }

        $this->merge($payload);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $parentId = $this->input('parent_id');
            if (! $parentId) {
                return;
            }

            /** @var CostCenter|null $parent */
            $parent = CostCenter::query()->find($parentId);
            if (! $parent) {
                return;
            }

            $current = $this->route('cost_center');
            $currentId = $current instanceof CostCenter ? $current->id : null;

            if ($currentId === null) {
                return;
            }

            if ($parent->id === $currentId) {
                $validator->errors()->add('parent_id', 'Um centro de custo não pode ser pai de si mesmo.');

                return;
            }

            $ancestor = $parent;
            while ($ancestor) {
                if ($ancestor->parent_id === null) {
                    break;
                }

                if ($ancestor->parent_id === $currentId) {
                    $validator->errors()->add('parent_id', 'Não é possível selecionar um descendente como pai.');

                    return;
                }

                $ancestor = $ancestor->parent()->first();
            }
        });
    }
}
