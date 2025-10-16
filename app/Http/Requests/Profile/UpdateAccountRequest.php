<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'nome' => ['required', 'string', 'max:120'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('usuarios', 'email')->ignore($userId),
            ],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }
}
