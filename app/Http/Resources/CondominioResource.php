<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CondominioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cnpj' => $this->cnpj,
            'cep' => $this->cep,
            'estado' => $this->estado,
            'cidade' => $this->cidade,
            'bairro' => $this->bairro,
            'rua' => $this->rua,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

