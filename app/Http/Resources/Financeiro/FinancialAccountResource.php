<?php

namespace App\Http\Resources\Financeiro;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'apelido' => $this->apelido,
            'tipo' => $this->tipo,
            'instituicao' => $this->instituicao,
            'banco' => $this->banco,
            'agencia' => $this->agencia,
            'numero' => $this->numero,
            'carteira' => $this->carteira,
            'moeda' => $this->moeda,
            'saldo_inicial' => $this->saldo_inicial,
            'data_saldo_inicial' => $this->data_saldo_inicial,
            'saldo_atual' => $this->saldo_atual,
            'limite_credito' => $this->limite_credito,
            'categoria' => $this->categoria,
            'permite_transf' => $this->permite_transf,
            'padrao_recebimento' => $this->padrao_recebimento,
            'padrao_pagamento' => $this->padrao_pagamento,
            'observacoes' => $this->observacoes,
            'ativo' => $this->ativo,
            'transactions_count' => $this->whenCounted('transactions'),
            'journal_entries_count' => $this->whenCounted('journalEntries'),
            'counter_journal_entries_count' => $this->whenCounted('counterJournalEntries'),
            'can_delete' => ($this->transactions_count ?? 0) === 0
                && ($this->journal_entries_count ?? 0) === 0
                && ($this->counter_journal_entries_count ?? 0) === 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
