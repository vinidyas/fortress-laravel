<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->hasPermission('admin.access') : false;
    }

    public function rules(): array
    {
        $routeRole = $this->route('role');
        $roleId = $routeRole instanceof Model ? $routeRole->getKey() : $routeRole;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('roles', 'slug')->ignore($roleId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $name = $this->input('name');

        if (($slug === null || $slug === '') && is_string($name)) {
            $slug = Str::slug($name);
        }

        $this->merge([
            'slug' => is_string($slug) ? Str::slug($slug) : null,
            'permissions' => $this->normalizeStringArray($this->input('permissions')),
        ]);
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
}
