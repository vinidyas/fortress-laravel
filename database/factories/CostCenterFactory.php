<?php

namespace Database\Factories;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    protected static int $sequence = 1;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->words(2, true),
            'descricao' => $this->faker->sentence(),
            'codigo' => sprintf('%d.0', self::$sequence++),
            'parent_id' => null,
            'tipo' => $this->faker->randomElement(['fixo', 'variavel', 'investimento']),
            'ativo' => true,
            'orcamento_anual' => $this->faker->randomFloat(2, 10000, 500000),
        ];
    }
}
