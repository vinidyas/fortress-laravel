<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ReportImoveisFilterRequest extends FormRequest
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
            'only_available' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:200'],
        ];
    }

    public function perPage(): int
    {
        return max(10, min(200, (int) $this->input('per_page', 50)));
    }
}
