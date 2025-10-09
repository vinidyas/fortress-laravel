<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\Fatura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fatura>
 */
class FaturaFactory extends Factory
{
    protected $model = Fatura::class;

    public function definition(): array
    {
        $competencia = fake()->dateTimeBetween('-3 months', '+1 month');
        $competencia = (clone $competencia)->modify('first day of this month');
        $vencimento = (clone $competencia)->modify('+'.fake()->numberBetween(5, 20).' days');

        return [
            'contrato_id' => Contrato::factory(),
            'competencia' => $competencia->format('Y-m-d'),
            'vencimento' => $vencimento->format('Y-m-d'),
            'status' => fake()->randomElement(['Aberta', 'Paga', 'Cancelada']),
            'valor_total' => fake()->randomFloat(2, 500, 5000),
            'valor_pago' => null,
        ];
    }
}
