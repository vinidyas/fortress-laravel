<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImovelAnexo extends Model
{
    use HasFactory;

    protected $table = 'imovel_anexos';

    protected $fillable = [
        'imovel_id',
        'path',
        'original_name',
        'display_name',
        'mime_type',
        'uploaded_by',
    ];

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
