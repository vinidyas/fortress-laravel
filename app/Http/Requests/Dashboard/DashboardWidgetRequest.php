<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'widgets' => ['required', 'array'],
            'widgets.*.key' => [
                'required',
                'string',
                Rule::in(array_keys(DashboardController::WIDGET_DEFINITIONS)),
            ],
            'widgets.*.hidden' => ['sometimes', 'boolean'],
            'widgets.*.position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
