<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaturaEmailLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'recipients' => $this->recipients ?? [],
            'cc' => $this->cc ?? [],
            'bcc' => $this->bcc ?? [],
            'attachments' => collect($this->attachments ?? [])
                ->map(fn ($attachment) => [
                    'id' => $attachment['id'] ?? null,
                    'original_name' => $attachment['original_name'] ?? null,
                    'display_name' => $attachment['display_name'] ?? ($attachment['original_name'] ?? null),
                    'mime_type' => $attachment['mime_type'] ?? null,
                    'size' => $attachment['size'] ?? null,
                ])->values()->all(),
            'message' => $this->message,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name ?? $this->user->nome,
                'nome' => $this->user->nome,
                'email' => $this->user->email,
            ]),
        ];
    }
}
