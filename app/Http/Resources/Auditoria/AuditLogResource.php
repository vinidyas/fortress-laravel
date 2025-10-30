<?php

namespace App\Http\Resources\Auditoria;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'payload' => $this->payload ?? [],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'context' => $this->context ?? [],
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'nome' => $this->user->nome,
                'username' => $this->user->username,
            ]),
        ];
    }
}
