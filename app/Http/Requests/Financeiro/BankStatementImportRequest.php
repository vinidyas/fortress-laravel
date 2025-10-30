<?php

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;

class BankStatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('financeiro.reconcile');
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'file' => ['required', 'file', 'mimes:csv,txt,ofx,qfx', 'max:5120'],
        ];
    }
}
