<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pessoa extends Model
{
    use HasFactory;

    protected $table = 'pessoas';

    protected $fillable = [
        'nome_razao_social',
        'cpf_cnpj',
        'email',
        'telefone',
        'cep',
        'estado',
        'cidade',
        'bairro',
        'rua',
        'numero',
        'complemento',
        'tipo_pessoa',
        'papeis',
        'dados_bancarios',
    ];

    protected $casts = [
        'papeis' => 'array',
        'dados_bancarios' => 'array',
    ];

    protected $attributes = [
        'papeis' => '[]',
        'dados_bancarios' => '[]',
    ];

    public function imoveisProprietario(): HasMany
    {
        return $this->hasMany(Imovel::class, 'proprietario_id');
    }

    public function imoveisAgenciados(): HasMany
    {
        return $this->hasMany(Imovel::class, 'agenciador_id');
    }

    public function imoveisResponsavel(): HasMany
    {
        return $this->hasMany(Imovel::class, 'responsavel_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'pessoa_id');
    }

    public function usuarioPortal(): HasOne
    {
        return $this->hasOne(User::class, 'pessoa_id')->latestOfMany();
    }

    public function isFisica(): bool
    {
        return $this->tipo_pessoa === 'Fisica';
    }

    public function hasPapel(string $papel): bool
    {
        $papeis = $this->papeis ?? [];

        return in_array($papel, $papeis, true);
    }

    public function getNomeAttribute(): string
    {
        return (string) ($this->attributes['nome_razao_social'] ?? '');
    }
}
