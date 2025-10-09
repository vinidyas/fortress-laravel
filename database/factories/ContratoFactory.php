<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    protected $model = Contrato::class;

    public function definition(): array
    {
        $dataInicio = fake()->dateTimeBetween('-1 year', 'now');
        $dataFim = fake()->boolean(30) ? fake()->dateTimeBetween($dataInicio, '+1 year') : null;

        return [
            'codigo_contrato' => strtoupper(fake()->bothify('CTR-#####')),
            'imovel_id' => Imovel::factory(),
            'locador_id' => Pessoa::factory(),
            'locatario_id' => Pessoa::factory(),
            'fiador_id' => fake()->boolean(50) ? Pessoa::factory() : null,
            'data_inicio' => $dataInicio->format('Y-m-d'),
            'data_fim' => $dataFim?->format('Y-m-d'),
            'dia_vencimento' => fake()->numberBetween(1, 28),
            'valor_aluguel' => fake()->randomFloat(2, 500, 10000),
            'reajuste_indice' => fake()->randomElement(['IGPM', 'IPCA', 'INPC']),
            'data_proximo_reajuste' => fake()->optional()->dateTimeBetween('now', '+1 year')?->format('Y-m-d'),
            'garantia_tipo' => fake()->randomElement(['Fiador', 'Seguro', 'Caucao', 'SemGarantia']),
            'caucao_valor' => fake()->optional()->randomFloat(2, 0, 5000),
            'taxa_adm_percentual' => fake()->randomFloat(2, 0, 20),
            'status' => fake()->randomElement(['Ativo', 'Suspenso', 'Encerrado']),
            'observacoes' => fake()->optional()->paragraph(),
        ];
    }
}
