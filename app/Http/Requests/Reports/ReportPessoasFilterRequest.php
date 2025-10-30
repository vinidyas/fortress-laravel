<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPessoasFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->hasPermission('reports.view.pessoas');
    }

    public function rules(): array
    {
        return [
            'papel' => ['nullable', 'string', 'max:60'],
            'tipo_pessoa' => ['nullable', 'string', 'in:Fisica,Juridica'],
            'format' => ['nullable', Rule::in(['csv'])],
        ];
    }
}
