<?php

namespace App\Http\Requests\Financeiro;

use App\Models\JournalEntryInstallment;
use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;

class JournalEntryInstallmentPayRequest extends FormRequest
{
    use NormalizesDecimalValues;

    public function authorize(): bool
    {
        /** @var JournalEntryInstallment|null $installment */
        $installment = $this->route('installment');
        $user = $this->user();

        if (! $user || ! $installment) {
            return false;
        }

        return $user->can('update', $installment->journalEntry);
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'penalty' => ['nullable', 'numeric', 'min:0'],
            'interest' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'penalty' => $this->normalizeDecimalToFloat($this->input('penalty', 0)),
            'interest' => $this->normalizeDecimalToFloat($this->input('interest', 0)),
            'discount' => $this->normalizeDecimalToFloat($this->input('discount', 0)),
        ]);
    }
}
