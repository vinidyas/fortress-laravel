<?php

namespace App\Http\Requests\Fatura;

use Illuminate\Foundation\Http\FormRequest;

class FaturaEmailRequest extends FormRequest
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
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['required', 'email:filter'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['required', 'email:filter'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['required', 'email:filter'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['integer', 'exists:fatura_anexos,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['recipients', 'cc', 'bcc'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $emails = collect(preg_split('/[,\s;]+/', $value))
                    ->map(fn ($email) => trim((string) $email))
                    ->filter(fn ($email) => $email !== '');

                $this->merge([
                    $field => $emails->values()->all(),
                ]);
            } elseif (is_array($value)) {
                $emails = collect($value)
                    ->map(fn ($email) => trim((string) $email))
                    ->filter(fn ($email) => $email !== '');

                $this->merge([
                    $field => $emails->values()->all(),
                ]);
            }
        }

        $attachments = $this->input('attachments');

        if (is_string($attachments)) {
            $parsed = collect(preg_split('/[,\s;]+/', $attachments))
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '' && is_numeric($value))
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();

            $this->merge([
                'attachments' => $parsed,
            ]);
        } elseif (is_array($attachments)) {
            $parsed = collect($attachments)
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();

            $this->merge([
                'attachments' => $parsed,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitized(): array
    {
        return [
            'recipients' => $this->input('recipients', []),
            'cc' => $this->input('cc', []),
            'bcc' => $this->input('bcc', []),
            'attachments' => $this->input('attachments', []),
            'message' => $this->input('message'),
        ];
    }
}
