<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->hasPermission('admin.access') : false;
    }

    public function rules(): array
    {
        $routeUser = $this->route('user');
        $userId = $routeUser instanceof Model ? $routeUser->getKey() : $routeUser;

        return [
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('usuarios', 'username')->ignore($userId),
            ],
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('usuarios', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
            'ativo' => ['sometimes', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'send_password_reset' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $roles = $this->input('roles');
        $permissions = $this->input('permissions');

        $this->merge([
            'roles' => $this->normalizeIntegerArray($roles),
            'permissions' => $this->normalizeStringArray($permissions),
            'ativo' => $this->has('ativo')
                ? filter_var($this->input('ativo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'role_id' => $this->normalizeInteger($this->input('role_id')),
            'email' => $this->normalizeEmail($this->input('email')),
            'remove_avatar' => filter_var($this->input('remove_avatar', false), FILTER_VALIDATE_BOOLEAN),
            'send_password_reset' => filter_var($this->input('send_password_reset', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @param  mixed  $value
     * @return array<int, int>
     */
    private function normalizeIntegerArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            return is_numeric($item) ? (int) $item : null;
        }, $value), fn ($item) => $item !== null));
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            return is_string($item) ? trim($item) : null;
        }, $value), fn ($item) => $item !== null && $item !== ''));
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = trim($value);

        return $email !== '' ? $email : null;
    }
}
