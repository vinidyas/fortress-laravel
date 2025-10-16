<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $directPermissions = $this->getDirectPermissions()->pluck('name')->values()->all();

        return [
            'id' => $this->id,
            'username' => $this->username,
            'nome' => $this->nome,
            'email' => $this->email,
            'ativo' => (bool) $this->ativo,
            'role_id' => $this->role_id,
            'avatar_url' => $this->avatar_url,
            'roles' => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug ?? null,
                'is_system' => (bool) ($role->is_system ?? false),
            ]),
            'direct_permissions' => $directPermissions,
            'custom_permissions' => (array) ($this->permissoes ?? []),
            'all_permissions' => $this->getAllPermissions()->pluck('name')->unique()->values()->all(),
            'last_login_at' => optional($this->last_login_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
