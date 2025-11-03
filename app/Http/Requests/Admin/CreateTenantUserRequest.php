<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->hasPermission('admin.access'));
    }

    public function rules(): array
    {
        return [
            'pessoa_id' => ['required', 'integer', 'exists:pessoas,id'],
            'email' => ['required', 'email', 'max:150'],
            'username' => ['nullable', 'string', 'max:50'],
        ];
    }
}
