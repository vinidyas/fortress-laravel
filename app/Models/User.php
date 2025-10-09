<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    protected $table = 'usuarios';

    protected $fillable = [
        'username',
        'password',
        'nome',
        'role_id',
        'permissoes',
        'ativo',
        'last_login_at',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'permissoes' => 'array',
        'ativo' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    protected string $guard_name = 'web';

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'password' => 'hashed',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function hasPermission(string $permission): bool
    {
        if (method_exists($this, 'hasPermissionTo')) {
            try {
                if ($this->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                // Silence and fallback to JSON column below.
            }
        }

        $permissions = (array) ($this->permissoes ?? []);

        return in_array($permission, $permissions, true);
    }
}