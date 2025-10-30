<?php

namespace Database\Factories;

use App\Models\Fatura;
use App\Models\FaturaLancamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaturaLancamento>
 */
class FaturaLancamentoFactory extends Factory
{
    protected $model = FaturaLancamento::class;

    public function definition(): array
    {
        $quantidade = fake()->randomFloat(2, 1, 3);
        $valorUnitario = fake()->randomFloat(2, 100, 2000);

        return [
            'fatura_id' => Fatura::factory(),
            'categoria' => fake()->randomElement(['Aluguel', 'Condominio', 'IPTU', 'Multa', 'Juros', 'Desconto', 'Outros']),
            'descricao' => fake()->optional()->sentence(4),
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $quantidade * $valorUnitario,
        ];
    }
}
