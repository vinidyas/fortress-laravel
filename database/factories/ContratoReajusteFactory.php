<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\ContratoReajuste;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContratoReajusteFactory extends Factory
{
    protected $model = ContratoReajuste::class;

    public function definition(): array
    {
        $percentual = $this->faker->randomFloat(2, 1, 10);
        $valorAnterior = $this->faker->randomFloat(2, 500, 5000);
        $valorReajuste = $valorAnterior * ($percentual / 100);
        $valorNovo = $valorAnterior + $valorReajuste;

        return [
            'contrato_id' => Contrato::factory(),
            'usuario_id' => User::factory(),
            'indice' => $this->faker->randomElement(['IGPM', 'IPCA', 'INPC']),
            'percentual_aplicado' => $percentual,
            'valor_anterior' => $valorAnterior,
            'valor_novo' => $valorNovo,
            'valor_reajuste' => $valorReajuste,
            'teto_percentual' => null,
            'data_base_reajuste' => now()->toDateString(),
            'data_proximo_reajuste_anterior' => now()->toDateString(),
            'data_proximo_reajuste_novo' => now()->addYear()->toDateString(),
            'observacoes' => $this->faker->optional(0.3)->sentence(),
        ];
    }
}
