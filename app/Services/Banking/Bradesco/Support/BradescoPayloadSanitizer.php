<?php

declare(strict_types=1);

namespace App\Services\Banking\Bradesco\Support;

use Illuminate\Support\Str;

class BradescoPayloadSanitizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
                continue;
            }

            if ($value === null) {
                $sanitized[$key] = null;
                continue;
            }

            if (! is_string($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            $sanitized[$key] = self::sanitizeScalar($key, $value);
        }

        return $sanitized;
    }

    protected static function sanitizeScalar(string|int $key, string $value): string
    {
        $normalizedKey = Str::of((string) $key)->snake()->lower()->value();

        if (self::isLinhaDigitavelKey($normalizedKey)) {
            return self::maskLinhaDigitavel($value);
        }

        if (self::isCodigoBarrasKey($normalizedKey)) {
            return self::maskLinhaDigitavel($value);
        }

        if (self::isEmailKey($normalizedKey)) {
            return self::maskEmail($value);
        }

        if (self::isPhoneKey($normalizedKey)) {
            return self::maskPhone($value);
        }

        if (self::isNameKey($normalizedKey)) {
            return self::maskName($value);
        }

        if (self::isDocumentKey($normalizedKey)) {
            return self::maskDocumento($value);
        }

        return $value;
    }

    protected static function isDocumentKey(string $key): bool
    {
        return Str::contains($key, [
            'cpf',
            'cnpj',
            'documento',
            'cliente',
            'pagador',
            'sacado',
            'sacador',
            'beneficiario',
            'controle',
            'raiz',
            'filial',
        ]);
    }

    protected static function isNameKey(string $key): bool
    {
        if (! Str::contains($key, 'nome')) {
            return false;
        }

        return Str::contains($key, ['pagador', 'sacado', 'sacador', 'avalista', 'beneficiario'])
            || $key === 'nome';
    }

    protected static function isPhoneKey(string $key): bool
    {
        return Str::contains($key, ['telefone', 'fone', 'celular']);
    }

    protected static function isEmailKey(string $key): bool
    {
        return Str::contains($key, ['email', 'eletronico']);
    }

    protected static function isLinhaDigitavelKey(string $key): bool
    {
        return Str::contains($key, ['linha_digitavel', 'linhadigitavel']);
    }

    protected static function isCodigoBarrasKey(string $key): bool
    {
        return Str::contains($key, ['codigo_barras', 'codigobarras']);
    }

    protected static function maskDocumento(string $documento): string
    {
        $digits = preg_replace('/\D+/', '', $documento) ?: '';

        if ($digits === '') {
            return self::maskFallback($documento);
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        $visibleStart = substr($digits, 0, 3);
        $visibleEnd = substr($digits, -3);
        $maskedLength = max(strlen($digits) - 6, 0);

        return $visibleStart . str_repeat('*', $maskedLength) . $visibleEnd;
    }

    protected static function maskName(string $nome): string
    {
        $length = mb_strlen($nome);

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        $first = mb_substr($nome, 0, 1);
        $last = mb_substr($nome, -1);
        $masked = str_repeat('*', $length - 2);

        return $first . $masked . $last;
    }

    protected static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return self::maskFallback($email);
        }

        [$local, $domain] = $parts;
        $local = $local === '' ? '*' : $local;

        $length = mb_strlen($local);
        $first = mb_substr($local, 0, 1);
        $last = $length > 1 ? mb_substr($local, -1) : '';
        $middle = str_repeat('*', max($length - 2, 1));

        return $first . $middle . $last . '@' . $domain;
    }

    protected static function maskPhone(string $telefone): string
    {
        $digits = preg_replace('/\D+/', '', $telefone) ?: '';

        if ($digits === '') {
            return self::maskFallback($telefone);
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        $start = substr($digits, 0, 2);
        $end = substr($digits, -2);
        $maskedLength = max(strlen($digits) - 4, 0);

        return $start . str_repeat('*', $maskedLength) . $end;
    }

    protected static function maskLinhaDigitavel(string $linha): string
    {
        $digits = preg_replace('/\D+/', '', $linha) ?: '';

        if ($digits === '') {
            return self::maskFallback($linha);
        }

        if (strlen($digits) <= 5) {
            return str_repeat('*', strlen($digits));
        }

        $start = substr($digits, 0, 4);
        $end = substr($digits, -4);
        $maskedLength = max(strlen($digits) - 8, 0);

        return $start . str_repeat('*', $maskedLength) . $end;
    }

    protected static function maskFallback(string $value): string
    {
        $length = mb_strlen($value);
        if ($length === 0) {
            return '';
        }

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 1)
            . str_repeat('*', $length - 2)
            . mb_substr($value, -1);
    }
}
