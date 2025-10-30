<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reports.view.financeiro') ?? false;
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'opening_balance' => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('opening_balance')) {
            $this->merge([
                'opening_balance' => (float) str_replace(',', '.', (string) $this->input('opening_balance')),
            ]);
        } else {
            $this->merge(['opening_balance' => null]);
        }
    }
}
