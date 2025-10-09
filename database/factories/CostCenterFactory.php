<?php

namespace Database\Factories;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->words(2, true),
            'descricao' => $this->faker->sentence(),
        ];
    }
}