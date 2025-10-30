<?php

namespace App\Http\Resources\Financeiro;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostCenterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'codigo' => $this->codigo,
            'parent_id' => $this->parent_id,
            'tipo' => $this->tipo,
            'ativo' => $this->ativo,
            'orcamento_anual' => $this->orcamento_anual,
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'nome' => $this->parent->nome,
                'codigo' => $this->parent->codigo,
            ]),
            'children' => $this->when(
                $this->relationLoaded('children') || $this->relationLoaded('childrenRecursive'),
                fn () => self::collection(
                    $this->relationLoaded('children') ? $this->children : $this->childrenRecursive
                )->resolve()
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
