<?php

namespace App\Http\Requests\Alert;

use Illuminate\Foundation\Http\FormRequest;

class DismissAlertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'keys' => ['required', 'array', 'min:1'],
            'keys.*' => ['string', 'max:160'],
        ];
    }
}
