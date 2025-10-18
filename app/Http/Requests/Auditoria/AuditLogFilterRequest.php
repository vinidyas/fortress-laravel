<?php

namespace App\Http\Requests\Auditoria;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->hasPermission('auditoria.view');
    }

    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer'],
            'auditable_type' => ['nullable', 'string', 'max:160'],
            'auditable_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'guard' => ['nullable', 'string', 'max:120'],
            'origin' => ['nullable', 'string', 'max:120'],
            'http_method' => ['nullable', 'string', 'max:12'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'format' => ['nullable', 'in:csv,json'],
        ];
    }
}
