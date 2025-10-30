<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaEmailLog extends Model
{
    use HasFactory;

    protected $table = 'fatura_email_logs';

    protected $fillable = [
        'fatura_id',
        'user_id',
        'subject',
        'recipients',
        'cc',
        'bcc',
        'attachments',
        'message',
        'status',
        'error_message',
    ];

    protected $casts = [
        'recipients' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'attachments' => 'array',
    ];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
