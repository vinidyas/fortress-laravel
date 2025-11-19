<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImovelReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $label = $this->formatLabel();
        $info = $this->formatInfo();

        return [
            'id' => $this->id,
            'label' => $label,
            'info' => $info,
            'tipo' => $this->tipo_imovel,
            'cidade' => $this->cidade,
            'valor_locacao' => (float) ($this->valor_locacao ?? 0),
            'dormitorios' => $this->dormitorios ?? 0,
            'vagas' => $this->vagas_garagem ?? 0,
            'disponibilidade' => $this->disponibilidade,
            'area_total' => $this->area_total,
        ];
    }

    private function formatLabel(): string
    {
        $condominio = trim((string) ($this->condominio?->nome ?? ''));
        $complemento = trim((string) ($this->complemento ?? ''));
        $base = $condominio !== '' ? $condominio : 'Sem condomínio';

        return $complemento !== '' ? "{$base} — {$complemento}" : $base;
    }

    private function formatInfo(): string
    {
        $parts = [];

        if (! empty($this->codigo)) {
            $parts[] = "Código {$this->codigo}";
        }

        if (! empty($this->cidade)) {
            $parts[] = $this->cidade;
        }

        if (! empty($this->bairro)) {
            $parts[] = $this->bairro;
        }

        return $parts !== [] ? implode(' • ', $parts) : '—';
    }
}
