<?php

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;

class CostCenterImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('financeiro.create');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx'],
        ];
    }
}