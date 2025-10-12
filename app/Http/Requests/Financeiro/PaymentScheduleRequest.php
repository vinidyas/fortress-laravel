<?php

namespace App\Http\Requests\Financeiro;

use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;

class PaymentScheduleRequest extends FormRequest
{
    use NormalizesDecimalValues;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $schedule = $this->route('payment_schedule');

        if ($this->isMethod('post')) {
            return $user->hasPermission('financeiro.create');
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return $user->can('update', $schedule);
        }

        if ($this->isMethod('delete')) {
            return $user->can('delete', $schedule);
        }

        return $user->hasPermission('financeiro.view');
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'valor_total' => ['required', 'numeric', 'min:0.01'],
            'parcela_atual' => ['required', 'integer', 'min:0'],
            'total_parcelas' => ['required', 'integer', 'min:1', 'gte:parcela_atual'],
            'vencimento' => ['required', 'date'],
            'status' => ['nullable', 'in:aberto,quitado,em_atraso,cancelado'],
            'meta' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'valor_total' => $this->normalizeDecimalToFloat($this->input('valor_total')),
        ]);
    }
}
