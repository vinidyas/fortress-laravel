<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaturaLancamentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoria' => $this->categoria,
            'descricao' => $this->descricao,
            'quantidade' => $this->quantidade,
            'valor_unitario' => $this->valor_unitario,
            'valor_total' => $this->valor_total,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
