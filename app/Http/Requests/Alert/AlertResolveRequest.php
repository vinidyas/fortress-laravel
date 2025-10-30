<?php

namespace App\Http\Requests\Alert;

use App\Models\DashboardAlert;
use Illuminate\Foundation\Http\FormRequest;

class AlertResolveRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DashboardAlert|null $alert */
        $alert = $this->route('dashboard_alert');

        return $alert ? $this->user()?->can('resolve', $alert) ?? false : false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
