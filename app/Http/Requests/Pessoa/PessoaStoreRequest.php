<?php

namespace App\Http\Requests\Pessoa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PessoaStoreRequest extends FormRequest
{
    private const PAPEIS_PERMITIDOS = [
        'Proprietario',
        'Inquilino',
        'Fiador',
        'Corretor',
        'Fornecedor',
        'Funcionario',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('pessoas.create');
    }

    public function rules(): array
    {
        $tipoPessoa = $this->input('tipo_pessoa');

        $cpfRules = ['nullable', 'string'];
        if ($tipoPessoa === 'Fisica') {
            $cpfRules[] = 'size:11';
        } elseif ($tipoPessoa === 'Juridica') {
            $cpfRules[] = 'size:14';
        }
        $cpfRules[] = Rule::unique('pessoas', 'cpf_cnpj');

        return [
            'nome_razao_social' => ['required', 'string', 'max:255'],
            'tipo_pessoa' => ['required', Rule::in(['Fisica', 'Juridica'])],
            'cpf_cnpj' => $cpfRules,
            'email' => ['nullable', 'string', 'max:150', 'email'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'papeis' => ['nullable', 'array'],
            'papeis.*' => [Rule::in(self::PAPEIS_PERMITIDOS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizedCpf = $this->normalizeCpfCnpj($this->input('cpf_cnpj'));
        $normalizedPapeis = $this->normalizePapeis($this->input('papeis'));

        $this->merge([
            'cpf_cnpj' => $normalizedCpf,
            'papeis' => $normalizedPapeis,
        ]);
    }

    private function normalizeCpfCnpj(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function normalizePapeis(mixed $value): array
    {
        $papeis = [];

        if (is_string($value)) {
            $papeis = array_map('trim', explode(',', $value));
        } elseif (is_array($value)) {
            $papeis = array_map(function ($item) {
                return is_string($item) ? trim($item) : $item;
            }, $value);
        }

        $papeis = array_filter($papeis, fn ($item) => is_string($item) && $item !== '');
        $papeis = array_unique(array_map(fn ($item) => ucfirst(strtolower($item)), $papeis));

        return array_values(array_intersect($papeis, self::PAPEIS_PERMITIDOS));
    }
}