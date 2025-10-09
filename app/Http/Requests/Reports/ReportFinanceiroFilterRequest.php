<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFinanceiroFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->hasPermission('reports.view.financeiro');
    }

    public function rules(): array
    {
        return [
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'status' => ['nullable', 'string', 'in:pendente,conciliado,cancelado'],
            'format' => ['nullable', Rule::in(['csv'])],
        ];
    }
}
