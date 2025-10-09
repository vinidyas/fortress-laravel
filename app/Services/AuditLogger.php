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
                'payload' => $payload,
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
}