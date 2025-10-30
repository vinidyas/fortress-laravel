<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportGeneralAnalyticRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('reports.view.financeiro');
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['despesa', 'receita', 'todos'])],
            'status' => ['nullable', Rule::in(['pago', 'em_aberto', 'todos'])],
            'date_basis' => ['nullable', Rule::in(['movement', 'due'])],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'description' => ['nullable', 'string', 'max:255'],
            'person_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'property_id' => ['nullable', 'integer', 'exists:imoveis,id'],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'financial_account_ids' => ['nullable', 'array'],
            'financial_account_ids.*' => ['integer', 'exists:financial_accounts,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'order_by' => ['nullable', Rule::in([
                'movement_date',
                'due_date',
                'person',
                'description',
                'notes',
                'document',
            ])],
            'order_desc' => ['nullable', 'boolean'],
            'preview_limit' => ['nullable', 'integer', 'min:10', 'max:200'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])],
        ];
    }
}
