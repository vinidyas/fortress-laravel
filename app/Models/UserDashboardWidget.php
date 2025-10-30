<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'widget_key',
        'position',
        'hidden',
    ];

    protected $casts = [
        'hidden' => 'boolean',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
