<?php

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;

class BankStatementConfirmMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('financeiro.reconcile');
    }

    public function rules(): array
    {
        return [
            'installment_id' => ['required', 'integer', 'exists:journal_entry_installments,id'],
            'payment_date' => ['required', 'date'],
        ];
    }
}
