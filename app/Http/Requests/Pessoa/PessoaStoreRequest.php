<?php

namespace App\Http\Requests\Pessoa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PessoaStoreRequest extends FormRequest
{
    private const PAPEIS_PERMITIDOS = [
        'Proprietario',
        'Locatario',
        'Fiador',
        'Fornecedor',
        'Cliente',
    ];

    private const PAPEIS_ALIASES = [
        'Inquilino' => 'Locatario',
        'Locatário' => 'Locatario',
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
            $cpfRules[] = function (string $attribute, $value, callable $fail) {
                if ($value !== null && $value !== '' && ! $this->isValidCpf((string) $value)) {
                    $fail('O CPF informado e invalido.');
                }
            };
        } elseif ($tipoPessoa === 'Juridica') {
            $cpfRules[] = 'size:14';
            $cpfRules[] = function (string $attribute, $value, callable $fail) {
                if ($value !== null && $value !== '' && ! $this->isValidCnpj((string) $value)) {
                    $fail('O CNPJ informado e invalido.');
                }
            };
        }
        $cpfRules[] = Rule::unique('pessoas', 'cpf_cnpj');

        return [
            'nome_razao_social' => ['required', 'string', 'max:255'],
            'tipo_pessoa' => ['required', Rule::in(['Fisica', 'Juridica'])],
            'cpf_cnpj' => $cpfRules,
            'email' => ['nullable', 'string', 'max:150', 'email'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'cep' => ['nullable', 'string', 'max:20'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'rua' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:150'],
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
        $papeis = array_unique(array_map(function ($item) {
            $normalized = ucfirst(mb_strtolower($item));

            return self::PAPEIS_ALIASES[$normalized] ?? $normalized;
        }, $papeis));

        return array_values(array_intersect($papeis, self::PAPEIS_PERMITIDOS));
    }

    private function isValidCpf(string $digits): bool
    {
        $cpf = preg_replace('/\D+/', '', $digits) ?? '';
        if (strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // DV1
        $sum = 0;
        for ($i = 0, $w = 10; $i < 9; $i++, $w--) {
            $sum += ((int) $cpf[$i]) * $w;
        }
        $remainder = $sum % 11;
        $dv1 = ($remainder < 2) ? 0 : 11 - $remainder;
        if ($dv1 !== (int) $cpf[9]) {
            return false;
        }

        // DV2
        $sum = 0;
        for ($i = 0, $w = 11; $i < 10; $i++, $w--) {
            $sum += ((int) $cpf[$i]) * $w;
        }
        $remainder = $sum % 11;
        $dv2 = ($remainder < 2) ? 0 : 11 - $remainder;

        return $dv2 === (int) $cpf[10];
    }

    private function isValidCnpj(string $digits): bool
    {
        $cnpj = preg_replace('/\D+/', '', $digits) ?? '';
        if (strlen($cnpj) !== 14) {
            return false;
        }
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $calcDv = function (string $base, array $weights): int {
            $sum = 0;
            $len = strlen($base);
            for ($i = 0; $i < $len; $i++) {
                $sum += ((int) $base[$i]) * $weights[$i];
            }
            $remainder = $sum % 11;

            return ($remainder < 2) ? 0 : 11 - $remainder;
        };

        $dv1 = $calcDv(substr($cnpj, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        if ($dv1 !== (int) $cnpj[12]) {
            return false;
        }
        $dv2 = $calcDv(substr($cnpj, 0, 13), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $dv2 === (int) $cnpj[13];
    }
}
