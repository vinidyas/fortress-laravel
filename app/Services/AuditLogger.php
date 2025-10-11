<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLogger
{
    public function record(string $action, ?Model $model = null, array $payload = []): void
    {
        try {
            $user = auth()->user();

            if (! $user instanceof Authenticatable) {
                $user = auth('sanctum')->user();
            }

            if (! $user instanceof Authenticatable) {
                $user = request()->user();
            }

            AuditLog::create([
                'user_id' => $user?->getAttribute('id'),
                'action' => $action,
                'auditable_type' => $model ? get_class($model) : null,
                'auditable_id' => $model?->getKey(),
                'payload' => $this->redactPayload($payload),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Falha ao registrar auditoria: '.$exception->getMessage(), [
                'action' => $action,
                'model' => $model ? get_class($model) : null,
            ]);
        }
    }

    public static function actionFor(string $event, Model $model): string
    {
        $base = Str::snake(class_basename($model));

        return $base.'.'.$event;
    }

    /**
     * Redige dados sensíveis no payload da auditoria (recursivo).
     * Campos-alvo: senha/password, token, cpf/cnpj, email, telefone, documento, pix.
     */
    private function redactPayload(array $payload): array
    {
        $keysToMask = [
            'password', 'senha', 'remember_token', 'token', 'access_token', 'refresh_token',
            'cpf', 'cpf_cnpj', 'cnpj', 'documento', 'document',
            'email', 'telefone', 'phone', 'pix', 'pix_qrcode',
        ];

        $maskKey = static function (string $key) use ($keysToMask): bool {
            $normalized = mb_strtolower($key);
            foreach ($keysToMask as $candidate) {
                if ($normalized === $candidate || str_contains($normalized, $candidate)) {
                    return true;
                }
            }

            return false;
        };

        $redactScalar = static function ($value, string $key) {
            if (! is_scalar($value)) {
                return $value;
            }

            $string = (string) $value;
            $lower = mb_strtolower($key);

            // Emails: mantém domínio parcialmente
            if (str_contains($lower, 'email')) {
                if (preg_match('/^([^@]+)@(.+)$/', $string, $m)) {
                    $user = $m[1];
                    $domain = $m[2];
                    $maskedUser = mb_substr($user, 0, 1).str_repeat('*', max(0, mb_strlen($user) - 1));

                    return $maskedUser.'@'.$domain;
                }
            }

            // Documentos/telefones/tokens: mantém últimos 2 dígitos
            $len = mb_strlen($string);
            if ($len <= 2) {
                return str_repeat('*', $len);
            }

            return str_repeat('*', max(0, $len - 2)).mb_substr($string, -2);
        };

        $walker = function ($data) use (&$walker, $maskKey, $redactScalar) {
            if (is_array($data)) {
                $result = [];
                foreach ($data as $k => $v) {
                    if (is_string($k) && $maskKey($k)) {
                        $result[$k] = $redactScalar($v, $k);
                    } else {
                        $result[$k] = is_array($v) ? $walker($v) : $v;
                    }
                }

                return $result;
            }

            return $data;
        };

        return $walker($payload);
    }
}
