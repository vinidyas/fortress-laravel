<?php

namespace App\Http\Requests\Fatura;

use Illuminate\Foundation\Http\FormRequest;

class FaturaAttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, string|null>
     */
    public function labels(): array
    {
        $labels = $this->input('labels', []);

        if (! is_array($labels)) {
            return [];
        }

        return collect($labels)
            ->map(fn ($label) => is_string($label) ? trim($label) : null)
            ->map(fn ($label) => $label === '' ? null : $label)
            ->values()
            ->all();
    }
}
