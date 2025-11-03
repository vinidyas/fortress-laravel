<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isPortalDomain()) {
            return [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
                'remember' => ['nullable', 'boolean'],
            ];
        }

        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->isPortalDomain()) {
            $this->merge([
                'email' => $this->normalizeEmail($this->input('email')),
                'remember' => $this->transformRemember($this->input('remember')),
            ]);

            return;
        }

        $this->merge([
            'username' => is_string($this->input('username')) ? trim((string) $this->input('username')) : $this->input('username'),
            'remember' => $this->transformRemember($this->input('remember')),
        ]);
    }

    private function transformRemember(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? null;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strtolower(trim((string) $value));
    }

    private function isPortalDomain(): bool
    {
        $portalDomain = config('app.portal_domain');

        return $portalDomain && $this->getHost() === $portalDomain;
    }
}
