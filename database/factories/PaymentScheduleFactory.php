<?php

namespace Database\Factories;

use App\Models\PaymentSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentScheduleFactory extends Factory
{
    protected $model = PaymentSchedule::class;

    public function definition(): array
    {
        $totalParcelas = $this->faker->numberBetween(1, 12);

        return [
            'titulo' => 'Parcela '.$this->faker->unique()->numerify('####'),
            'valor_total' => $this->faker->randomFloat(2, 100, 10000),
            'parcela_atual' => 0,
            'total_parcelas' => $totalParcelas,
            'vencimento' => $this->faker->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'status' => 'aberto',
            'meta' => [],
        ];
    }
}
