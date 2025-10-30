<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankApiConfig extends Model
{
    use HasFactory;

    protected $table = 'bank_api_configs';

    protected $fillable = [
        'bank_code',
        'environment',
        'client_id',
        'client_secret',
        'certificate_path',
        'certificate_password',
        'webhook_secret',
        'settings',
        'access_token',
        'token_expires_at',
        'active',
    ];

    protected $casts = [
        'settings' => 'array',
        'token_expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }

    public function shouldRefreshToken(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast() || now()->diffInMinutes($this->token_expires_at, false) < 5;
    }
}
