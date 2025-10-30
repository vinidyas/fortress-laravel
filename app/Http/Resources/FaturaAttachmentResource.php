<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FaturaAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'display_name' => $this->display_name ?? $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_at' => optional($this->created_at)->toIso8601String(),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader?->id,
                'name' => $this->uploader?->name ?? $this->uploader?->nome,
                'email' => $this->uploader?->email,
            ]),
            'url' => Storage::disk('public')->url($this->path),
        ];
    }
}
