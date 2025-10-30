<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportBankStatementFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->hasPermission('reports.view.financeiro');
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'status' => ['nullable', 'string', Rule::in([
                'processando',
                'importado',
                'conciliado',
                'erro',
                'open',
                'reconciled',
                'processing',
                'error',
            ])],
            'reference' => ['nullable', 'string', 'max:120'],
            'imported_at_from' => ['nullable', 'date'],
            'imported_at_to' => ['nullable', 'date'],
            'with_lines' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'format' => ['nullable', Rule::in(['csv'])],
        ];
    }
}
