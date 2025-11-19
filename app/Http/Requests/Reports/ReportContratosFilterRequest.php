<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportContratosFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasPermission('reports.view.operacional') || $user?->hasPermission('reports.view.financeiro'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'only_active' => ['sometimes', 'boolean'],
            'date_field' => ['sometimes', Rule::in(['inicio', 'fim'])],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:200'],
        ];
    }

    public function perPage(): int
    {
        $perPage = (int) $this->input('per_page', 50);

        return max(10, min(200, $perPage));
    }

    public function dateField(): string
    {
        return $this->input('date_field', 'inicio');
    }

    public function filterField(): string
    {
        return $this->dateField() === 'fim' ? 'data_fim' : 'data_inicio';
    }
}
