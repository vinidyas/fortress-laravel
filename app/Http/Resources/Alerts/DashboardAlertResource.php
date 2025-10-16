<?php

namespace App\Http\Resources\Alerts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'category' => $this->category,
            'severity' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            'resource' => [
                'type' => $this->resource_type,
                'id' => $this->resource_id,
            ],
            'payload' => $this->payload ?? [],
            'occurred_at' => optional($this->occurred_at)?->toDateTimeString(),
            'resolved_at' => optional($this->resolved_at)?->toDateTimeString(),
            'resolved_by' => $this->resolvedBy
                ? [
                    'id' => $this->resolvedBy->id,
                    'name' => $this->resolvedBy->nome,
                    'username' => $this->resolvedBy->username,
                ]
                : null,
            'resolution_notes' => $this->resolution_notes,
        ];
    }
}
