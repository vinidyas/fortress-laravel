<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntryDescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'texto',
        'uso_total',
        'ultima_utilizacao',
    ];

    protected $casts = [
        'uso_total' => 'integer',
        'ultima_utilizacao' => 'datetime',
    ];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'description_id');
    }
}
