<?php

declare(strict_types=1);

namespace App\Console\Commands\Finance\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class MigrationLogger
{
    public function success(string $entity, int $id): void
    {
        Log::channel('single')->info("[finance-migrate] {$entity} {$id} migrado com sucesso");
    }

    public function failure(string $entity, int $id, Throwable $exception): void
    {
        Log::channel('single')->error("[finance-migrate] {$entity} {$id} falhou: {$exception->getMessage()}", [
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
