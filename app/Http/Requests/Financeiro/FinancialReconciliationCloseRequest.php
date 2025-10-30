<?php

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;

class FinancialReconciliationCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('financeiro.reconcile');
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
            'statement_ids' => ['sometimes', 'array'],
            'statement_ids.*' => ['integer', 'exists:bank_statements,id'],
        ];
    }
}
