<?php

namespace App\Http\Requests\Alert;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlertHistoryFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('alerts.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', 'resolved', 'all'])],
            'category' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }
}
