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
        $papeis = $this->input('papeis', []);
        $requiresBoletoData = is_array($papeis) && in_array('Locatario', $papeis, true);

        $cpfRules = ['required', 'string'];
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

        $telefoneRules = $requiresBoletoData
            ? ['required', 'string', 'max:30', $this->telefoneRule()]
            : ['nullable', 'string', 'max:30', $this->telefoneRule(true)];

        $cepRules = $requiresBoletoData ? ['required', 'string', 'size:8'] : ['nullable', 'string', 'max:20'];
        $estadoRules = $requiresBoletoData ? ['required', 'string', 'size:2'] : ['nullable', 'string', 'size:2'];
        $cidadeRules = $requiresBoletoData ? ['required', 'string', 'max:120'] : ['nullable', 'string', 'max:120'];
        $bairroRules = $requiresBoletoData ? ['required', 'string', 'max:120'] : ['nullable', 'string', 'max:120'];
        $ruaRules = $requiresBoletoData ? ['required', 'string', 'max:150'] : ['nullable', 'string', 'max:150'];
        $numeroRules = $requiresBoletoData ? ['required', 'string', 'max:20'] : ['nullable', 'string', 'max:20'];
        $emailRules = $requiresBoletoData ? ['required', 'string', 'max:150', 'email'] : ['nullable', 'string', 'max:150', 'email'];

        return [
            'nome_razao_social' => ['required', 'string', 'max:255'],
            'tipo_pessoa' => ['required', Rule::in(['Fisica', 'Juridica'])],
            'cpf_cnpj' => $cpfRules,
            'email' => $emailRules,
            'telefone' => $telefoneRules,
            'cep' => $cepRules,
            'estado' => $estadoRules,
            'cidade' => $cidadeRules,
            'bairro' => $bairroRules,
            'rua' => $ruaRules,
            'numero' => $numeroRules,
            'complemento' => ['nullable', 'string', 'max:150'],
            'papeis' => ['nullable', 'array'],
            'papeis.*' => [Rule::in(self::PAPEIS_PERMITIDOS)],
            'dados_bancarios' => ['nullable', 'array'],
            'dados_bancarios.banco' => ['nullable', 'string', 'max:120'],
            'dados_bancarios.agencia' => ['nullable', 'string', 'max:20'],
            'dados_bancarios.conta' => ['nullable', 'string', 'max:30'],
            'dados_bancarios.tipo_conta' => ['nullable', Rule::in(['corrente', 'poupanca', 'pagamento'])],
            'dados_bancarios.titular' => ['nullable', 'string', 'max:255'],
            'dados_bancarios.documento_titular' => ['nullable', 'string', 'max:14'],
            'dados_bancarios.pix_chave' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf_cnpj.size' => 'Informe 11 dígitos para CPF ou 14 dígitos para CNPJ.',
            'cep.size' => 'Informe um CEP com 8 dígitos.',
            'estado.size' => 'Informe a sigla do estado com 2 letras (ex.: SP).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizedCpf = $this->normalizeCpfCnpj($this->input('cpf_cnpj'));
        $normalizedPapeis = $this->normalizePapeis($this->input('papeis'));
        $normalizedCep = $this->digits($this->input('cep'));
        $normalizedTelefone = $this->digits($this->input('telefone'));
        $normalizedEstado = $this->normalizeEstado($this->input('estado'));
        $normalizedBankData = $this->normalizeBankData($this->input('dados_bancarios', []));

        $this->merge([
            'cpf_cnpj' => $normalizedCpf,
            'papeis' => $normalizedPapeis,
            'cep' => $normalizedCep,
            'telefone' => $normalizedTelefone,
            'estado' => $normalizedEstado,
            'dados_bancarios' => $normalizedBankData,
        ]);
    }

    private function normalizeCpfCnpj(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = $this->digits($value);

        return $digits === '' ? null : $digits;
    }

    private function normalizePapeis(mixed $value): array
    {
        $rawItems = [];

        if (is_array($value)) {
            $rawItems = $value;
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $rawItems = $decoded;
            } else {
                $rawItems = explode(',', $trimmed);
            }
        } elseif ($value === null) {
            return [];
        } else {
            $rawItems = [(string) $value];
        }

        $normalized = [];
        foreach ($rawItems as $item) {
            if (! is_string($item)) {
                continue;
            }

            $candidate = trim($item, " \t\n\r\0\x0B\"'");
            if ($candidate === '') {
                continue;
            }

            $normalized[] = $this->normalizePapelString($candidate);
        }

        $normalized = array_filter($normalized);
        $normalized = array_unique($normalized);

        return array_values(array_intersect($normalized, self::PAPEIS_PERMITIDOS));
    }

    private function normalizeBankData(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $tipoConta = isset($data['tipo_conta']) ? strtolower(trim((string) $data['tipo_conta'])) : null;
        $allowedTipoConta = ['corrente', 'poupanca', 'pagamento'];
        if (! in_array($tipoConta, $allowedTipoConta, true)) {
            $tipoConta = null;
        }

        return array_filter([
            'banco' => $this->nullableString($data['banco'] ?? null),
            'agencia' => $this->nullableString($data['agencia'] ?? null),
            'conta' => $this->nullableString($data['conta'] ?? null),
            'tipo_conta' => $tipoConta,
            'titular' => $this->nullableString($data['titular'] ?? null),
            'documento_titular' => $this->digits($data['documento_titular'] ?? null) ?: null,
            'pix_chave' => $this->nullableString($data['pix_chave'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePapelString(string $papel): string
    {
        $normalized = ucfirst(mb_strtolower($papel));

        return self::PAPEIS_ALIASES[$normalized] ?? $normalized;
    }

    private function telefoneRule(bool $nullable = false): \Closure
    {
        return function (string $attribute, $value, callable $fail) use ($nullable) {
            if ($nullable && ($value === null || $value === '')) {
                return;
            }

            $digits = $this->digits($value);
            if (strlen($digits) < 10 || strlen($digits) > 11) {
                $fail('Informe um telefone com DDD contendo 10 ou 11 dígitos.');
            }
        };
    }

    private function isValidCpf(string $digits): bool
    {
        $cpf = $this->digits($digits);
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
        $cnpj = $this->digits($digits);
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

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normalizeEstado(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : strtoupper(substr($trimmed, 0, 2));
    }
}
