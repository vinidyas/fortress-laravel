<?php

declare(strict_types=1);

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;

class ApplyContratoReajusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        /** @var \App\Models\Contrato|null $contrato */
        $contrato = $this->route('contrato');

        if (! $user || ! $contrato instanceof Contrato) {
            return false;
        }

        return $user->can('update', $contrato);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'percentual' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'valor_novo' => ['nullable', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
