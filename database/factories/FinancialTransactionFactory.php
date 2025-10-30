<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialTransactionFactory extends Factory
{
    protected $model = FinancialTransaction::class;

    public function definition(): array
    {
        return [
            'account_id' => FinancialAccount::factory(),
            'cost_center_id' => CostCenter::factory(),
            'tipo' => $this->faker->randomElement(['credito', 'debito']),
            'valor' => $this->faker->randomFloat(2, 50, 5000),
            'data_ocorrencia' => $this->faker->date(),
            'descricao' => $this->faker->sentence(4),
            'status' => 'pendente',
            'meta' => [],
        ];
    }

    public function conciliado(): self
    {
        return $this->state(fn () => ['status' => 'conciliado']);
    }
}
