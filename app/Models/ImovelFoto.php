<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImovelFoto extends Model
{
    use HasFactory;

    protected $table = 'imovel_fotos';

    protected $fillable = [
        'imovel_id',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'size',
        'ordem',
        'legenda',
        'width',
        'height',
    ];

    protected $casts = [
        'size' => 'int',
        'ordem' => 'int',
        'width' => 'int',
        'height' => 'int',
    ];

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }
}
