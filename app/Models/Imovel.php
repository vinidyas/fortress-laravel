<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Imovel extends Model
{
    use HasFactory;

    protected $table = 'imoveis';

    protected $fillable = [
        'codigo',
        'proprietario_id',
        'agenciador_id',
        'responsavel_id',
        'tipo_imovel',
        'finalidade',
        'disponibilidade',
        'cep',
        'estado',
        'cidade',
        'bairro',
        'rua',
        'condominio_id',
        'logradouro',
        'numero',
        'complemento',
        'valor_locacao',
        'valor_condominio',
        'condominio_isento',
        'valor_iptu',
        'iptu_isento',
        'outros_valores',
        'outros_isento',
        'periodo_iptu',
        'dormitorios',
        'suites',
        'banheiros',
        'vagas_garagem',
        'area_total',
        'area_construida',
        'comodidades',
    ];

    protected $casts = [
        'finalidade' => 'array',
        'comodidades' => 'array',
        'condominio_isento' => 'boolean',
        'iptu_isento' => 'boolean',
        'outros_isento' => 'boolean',
        'valor_locacao' => 'decimal:2',
        'valor_condominio' => 'decimal:2',
        'valor_iptu' => 'decimal:2',
        'outros_valores' => 'decimal:2',
        'area_total' => 'decimal:2',
        'area_construida' => 'decimal:2',
        'dormitorios' => 'integer',
        'suites' => 'integer',
        'banheiros' => 'integer',
        'vagas_garagem' => 'integer',
    ];

    public function proprietario(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'proprietario_id');
    }

    public function agenciador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'agenciador_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'responsavel_id');
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class, 'condominio_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ImovelAnexo::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ImovelFoto::class)->orderBy('ordem');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Imovel $imovel) {
            $imovel->anexos()->each(function (ImovelAnexo $anexo) {
                Storage::disk('public')->delete($anexo->path);
            });
            $imovel->fotos()->each(function (ImovelFoto $foto) {
                Storage::disk('public')->delete([$foto->path, $foto->thumbnail_path]);
            });
        });
    }
}
