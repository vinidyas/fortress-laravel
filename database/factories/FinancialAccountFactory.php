<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->company().' Conta',
            'tipo' => $this->faker->randomElement(['conta_corrente', 'caixa', 'outro']),
            'banco' => $this->faker->company,
            'agencia' => (string) $this->faker->randomNumber(4),
            'numero' => (string) $this->faker->randomNumber(6, true),
            'saldo_inicial' => $this->faker->randomFloat(2, 0, 50000),
            'ativo' => true,
        ];
    }
}