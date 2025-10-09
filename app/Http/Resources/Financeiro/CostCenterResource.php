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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}