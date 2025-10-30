<?php

namespace Database\Factories;

use App\Models\Condominio;
use App\Models\Imovel;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Imovel>
 */
class ImovelFactory extends Factory
{
    protected $model = Imovel::class;

    public function definition(): array
    {
        return [
            'codigo' => str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'proprietario_id' => Pessoa::factory(),
            'agenciador_id' => Pessoa::factory(),
            'responsavel_id' => Pessoa::factory(),
            'tipo_imovel' => fake()->randomElement(['Casa', 'Apartamento', 'Sala Comercial']),
            'finalidade' => ['Locacao'],
            'disponibilidade' => fake()->randomElement(['Disponivel', 'Indisponivel']),
            'cep' => fake()->postcode(),
            'estado' => strtoupper(fake()->lexify('??')),
            'cidade' => fake()->city(),
            'bairro' => fake()->streetName(),
            'rua' => fake()->streetName(),
            'condominio_id' => Condominio::factory(),
            'logradouro' => fake()->streetName(),
            'numero' => (string) fake()->numberBetween(1, 9999),
            'complemento' => fake()->optional()->sentence(3),
            'valor_locacao' => fake()->randomFloat(2, 500, 10000),
            'valor_condominio' => fake()->randomFloat(2, 0, 2000),
            'condominio_isento' => false,
            'valor_iptu' => fake()->randomFloat(2, 0, 2000),
            'iptu_isento' => false,
            'outros_valores' => fake()->randomFloat(2, 0, 500),
            'outros_isento' => false,
            'periodo_iptu' => fake()->randomElement(['Mensal', 'Anual']),
            'dormitorios' => fake()->numberBetween(0, 6),
            'suites' => fake()->numberBetween(0, 3),
            'banheiros' => fake()->numberBetween(1, 4),
            'vagas_garagem' => fake()->numberBetween(0, 4),
            'area_total' => fake()->randomFloat(2, 50, 400),
            'area_construida' => fake()->randomFloat(2, 40, 300),
            'comodidades' => fake()->randomElements([
                'Piscina',
                'Churrasqueira',
                'Academia',
                'Elevador',
            ], fake()->numberBetween(1, 3)),
        ];
    }
}
