<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ImovelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo_imovel' => $this->tipo_imovel,
            'finalidade' => $this->finalidade,
            'disponibilidade' => $this->disponibilidade,
            'enderecos' => [
                'cep' => $this->cep,
                'estado' => $this->estado,
                'cidade' => $this->cidade,
                'bairro' => $this->bairro,
                'rua' => $this->rua,
                'logradouro' => $this->logradouro,
                'numero' => $this->numero,
                'complemento' => $this->complemento,
            ],
            'valores' => [
                'valor_locacao' => $this->valor_locacao,
                'valor_condominio' => $this->valor_condominio,
                'condominio_isento' => $this->condominio_isento,
                'valor_iptu' => $this->valor_iptu,
                'iptu_isento' => $this->iptu_isento,
                'outros_valores' => $this->outros_valores,
                'outros_isento' => $this->outros_isento,
                'periodo_iptu' => $this->periodo_iptu,
            ],
            'caracteristicas' => [
                'dormitorios' => $this->dormitorios,
                'suites' => $this->suites,
                'banheiros' => $this->banheiros,
                'vagas_garagem' => $this->vagas_garagem,
                'area_total' => $this->area_total,
                'area_construida' => $this->area_construida,
                'comodidades' => $this->comodidades,
            ],
            'anexos_count' => $this->when(isset($this->anexos_count), fn () => (int) $this->anexos_count),
            'fotos_count' => $this->when(isset($this->fotos_count), fn () => (int) $this->fotos_count),
            'contratos' => $this->whenLoaded('contratos', fn () => $this->contratos->map(fn ($contrato) => [
                'id' => $contrato->id,
                'codigo_contrato' => $contrato->codigo_contrato,
                'status' => $contrato->status,
            ])),
            'proprietario' => $this->whenLoaded('proprietario', fn () => [
                'id' => $this->proprietario->id,
                'nome_razao_social' => $this->proprietario->nome_razao_social,
            ]),
            'agenciador' => $this->whenLoaded('agenciador', fn () => [
                'id' => $this->agenciador?->id,
                'nome_razao_social' => $this->agenciador?->nome_razao_social,
            ]),
            'responsavel' => $this->whenLoaded('responsavel', fn () => [
                'id' => $this->responsavel?->id,
                'nome_razao_social' => $this->responsavel?->nome_razao_social,
            ]),
            'condominio' => $this->whenLoaded('condominio', fn () => [
                'id' => $this->condominio?->id,
                'nome' => $this->condominio?->nome,
            ]),
            'anexos' => $this->whenLoaded('anexos', fn () => $this->anexos->map(function ($anexo) {
                return [
                    'id' => $anexo->id,
                    'display_name' => $anexo->display_name ?? $anexo->original_name,
                    'original_name' => $anexo->original_name,
                    'mime_type' => $anexo->mime_type,
                    'uploaded_at' => optional($anexo->created_at)->toIso8601String(),
                    'uploaded_by' => $anexo->uploader
                        ? [
                            'id' => $anexo->uploader->id,
                            'name' => $anexo->uploader->name,
                        ]
                        : null,
                    'url' => Storage::disk('public')->url($anexo->path),
                ];
            })),
            'fotos' => $this->whenLoaded('fotos', fn () => $this->fotos->map(function ($foto) {
                return [
                    'id' => $foto->id,
                    'legenda' => $foto->legenda,
                    'original_name' => $foto->original_name,
                    'mime_type' => $foto->mime_type,
                    'size' => $foto->size,
                    'ordem' => $foto->ordem,
                    'width' => $foto->width,
                    'height' => $foto->height,
                    'url' => Storage::disk('public')->url($foto->path),
                    'thumbnail_url' => Storage::disk('public')->url($foto->thumbnail_path),
                ];
            })),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
