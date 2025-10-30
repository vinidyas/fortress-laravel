<?php

namespace Database\Factories;

use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Condominio>
 */
class CondominioFactory extends Factory
{
    protected $model = Condominio::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'cep' => fake()->postcode(),
            'estado' => strtoupper(fake()->lexify('??')),
            'cidade' => fake()->city(),
            'bairro' => fake()->streetName(),
            'rua' => fake()->streetName(),
            'numero' => (string) fake()->numberBetween(1, 9999),
            'complemento' => fake()->optional()->sentence(3),
            'telefone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'observacoes' => fake()->optional()->paragraph(),
        ];
    }
}
