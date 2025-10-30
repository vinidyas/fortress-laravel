<?php

namespace App\Services\Boleto;

use App\Models\Fatura;
use App\Models\FaturaBoleto;

interface BoletoGateway
{
    /**
     * Registra um novo boleto junto ao provedor.
     *
     * @param  array<string, mixed>  $contexto Dados complementares (juros, multa, instrucoes).
     */
    public function issue(Fatura $fatura, array $contexto = []): FaturaBoleto;

    /**
     * Atualiza o status do boleto consultando o provedor.
     */
    public function refreshStatus(FaturaBoleto $boleto): FaturaBoleto;

    /**
     * Solicita cancelamento do boleto no provedor.
     *
     * @param  array<string, mixed>  $contexto Motivos ou metadados de cancelamento.
     */
    public function cancel(FaturaBoleto $boleto, array $contexto = []): FaturaBoleto;
}
