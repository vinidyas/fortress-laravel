<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->logger()->record(
            AuditLogger::actionFor('created', $model),
            $model,
            ['after' => $this->visibleAttributes($model)]
        );
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if (empty($changes)) {
            return;
        }

        $this->logger()->record(
            AuditLogger::actionFor('updated', $model),
            $model,
            [
                'before' => Arr::only($model->getOriginal(), array_keys($changes)),
                'after' => Arr::only($model->getAttributes(), array_keys($changes)),
            ]
        );
    }

    public function deleted(Model $model): void
    {
        $this->logger()->record(
            AuditLogger::actionFor('deleted', $model),
            $model,
            ['before' => $this->visibleAttributes($model)]
        );
    }

    protected function visibleAttributes(Model $model): array
    {
        return Arr::except($model->getAttributes(), $model->getHidden());
    }

    protected function logger(): AuditLogger
    {
        return app(AuditLogger::class);
    }
}
