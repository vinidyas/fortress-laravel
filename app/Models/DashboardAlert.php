<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'category',
        'severity',
        'title',
        'message',
        'resource_type',
        'resource_id',
        'payload',
        'occurred_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }
}
