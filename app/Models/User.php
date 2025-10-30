<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\UserAlert;
use App\Models\UserDashboardWidget;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'username',
        'password',
        'nome',
        'email',
        'role_id',
        'permissoes',
        'ativo',
        'last_login_at',
        'remember_token',
        'avatar_path',
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

    protected $appends = [
        'avatar_url',
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

    public function dismissedAlerts(): HasMany
    {
        return $this->hasMany(UserAlert::class);
    }

    public function dashboardWidgets(): HasMany
    {
        return $this->hasMany(UserDashboardWidget::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function routeNotificationForMail(): ?string
    {
        if ($this->email) {
            return $this->email;
        }

        if (filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            return $this->username;
        }

        return null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token, $this->username));
    }

    public function getEmailForPasswordReset(): ?string
    {
        return $this->email ?? $this->username;
    }
}
