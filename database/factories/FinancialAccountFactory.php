<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    public function definition(): array
    {
        $saldoInicial = $this->faker->randomFloat(2, 0, 50000);

        return [
            'nome' => $this->faker->unique()->company().' Conta',
            'apelido' => $this->faker->lexify('Conta ??'),
            'tipo' => $this->faker->randomElement(['conta_corrente', 'poupanca', 'investimento', 'caixa', 'outro']),
            'instituicao' => $this->faker->company,
            'banco' => $this->faker->company,
            'agencia' => (string) $this->faker->randomNumber(4),
            'numero' => (string) $this->faker->randomNumber(6, true),
            'carteira' => $this->faker->boolean ? (string) $this->faker->randomNumber(3) : null,
            'moeda' => 'BRL',
            'saldo_inicial' => $saldoInicial,
            'data_saldo_inicial' => now()->subMonths(rand(0, 6))->toDateString(),
            'saldo_atual' => $saldoInicial,
            'categoria' => $this->faker->randomElement(['operacional', 'reserva', 'investimento']),
            'permite_transf' => true,
            'padrao_recebimento' => false,
            'padrao_pagamento' => false,
            'ativo' => true,
        ];
    }
}
