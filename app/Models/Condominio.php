<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Condominio extends Model
{
    use HasFactory;

    protected $table = 'condominios';

    protected $fillable = [
        'nome',
        'cnpj',
        'cep',
        'estado',
        'cidade',
        'bairro',
        'rua',
        'numero',
        'complemento',
        'telefone',
        'email',
        'observacoes',
    ];

    public function imoveis(): HasMany
    {
        return $this->hasMany(Imovel::class, 'condominio_id');
    }
}
