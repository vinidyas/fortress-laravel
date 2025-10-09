<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportOperacionalFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->hasPermission('reports.view.operacional');
    }

    public function rules(): array
    {
        return [
            'cidade' => ['nullable', 'string', 'max:120'],
            'condominio_id' => ['nullable', 'integer', 'exists:condominios,id'],
            'status_contrato' => ['nullable', 'string', 'in:Ativo,Suspenso,Encerrado'],
            'ate' => ['nullable', 'date'],
            'format' => ['nullable', Rule::in(['csv'])],
        ];
    }
}
