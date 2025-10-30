<?php

namespace Database\Factories;

use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pessoa>
 */
class PessoaFactory extends Factory
{
    protected $model = Pessoa::class;

    public function definition(): array
    {
        $tipo = fake()->randomElement(['Fisica', 'Juridica']);

        return [
            'nome_razao_social' => $tipo === 'Fisica' ? fake()->name() : fake()->company(),
            'cpf_cnpj' => $tipo === 'Fisica'
                ? fake()->numerify('###########')
                : fake()->numerify('##############'),
            'email' => fake()->safeEmail(),
            'telefone' => fake()->phoneNumber(),
            'tipo_pessoa' => $tipo,
            'papeis' => [fake()->randomElement(['Proprietario', 'Locatario', 'Fiador', 'Fornecedor', 'Cliente'])],
        ];
    }
}
