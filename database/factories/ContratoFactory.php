<?php

namespace Database\Factories;

use App\Enums\ContratoFormaPagamento;
use App\Enums\ContratoGarantiaTipo;
use App\Enums\ContratoReajusteIndice;
use App\Enums\ContratoStatus;
use App\Enums\ContratoTipo;
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
        $dataFim = fake()->boolean(30) ? fake()->dateTimeBetween($dataInicio, '+2 years') : null;
        $garantia = fake()->randomElement(ContratoGarantiaTipo::values());
        $reajusteIndice = fake()->randomElement(ContratoReajusteIndice::values());

        return [
            'codigo_contrato' => strtoupper(fake()->bothify('CTR-#####')),
            'imovel_id' => Imovel::factory(),
            'locador_id' => Pessoa::factory(),
            'locatario_id' => Pessoa::factory(),
            'data_inicio' => $dataInicio->format('Y-m-d'),
            'data_fim' => $dataFim?->format('Y-m-d'),
            'dia_vencimento' => fake()->numberBetween(1, 28),
            'prazo_meses' => fake()->optional()->numberBetween(6, 60),
            'carencia_meses' => fake()->optional()->numberBetween(0, 6),
            'data_entrega_chaves' => fake()->optional()->dateTimeBetween($dataInicio, $dataFim ?? '+1 year')?->format('Y-m-d'),
            'valor_aluguel' => fake()->randomFloat(2, 500, 15000),
            'desconto_mensal' => fake()->optional()->randomFloat(2, 0, 1000),
            'reajuste_indice' => $reajusteIndice,
            'reajuste_indice_outro' => $reajusteIndice === ContratoReajusteIndice::Outro->value ? fake()->words(3, true) : null,
            'reajuste_periodicidade_meses' => fake()->numberBetween(6, 24),
            'reajuste_teto_percentual' => fake()->optional()->randomFloat(2, 1, 15),
            'data_proximo_reajuste' => fake()->optional()->dateTimeBetween('now', '+1 year')?->format('Y-m-d'),
            'garantia_tipo' => $garantia,
            'caucao_valor' => $garantia === ContratoGarantiaTipo::Caucao->value ? fake()->randomFloat(2, 500, 5000) : null,
            'taxa_adm_percentual' => fake()->optional()->randomFloat(2, 0, 20),
            'multa_atraso_percentual' => fake()->optional()->randomFloat(2, 0, 10),
            'juros_mora_percentual_mes' => fake()->optional()->randomFloat(2, 0, 5),
            'multa_rescisao_alugueis' => fake()->randomFloat(1, 1, 6),
            'repasse_automatico' => fake()->boolean(60),
            'conta_cobranca_id' => null,
            'forma_pagamento_preferida' => fake()->randomElement(ContratoFormaPagamento::values()),
            'tipo_contrato' => fake()->randomElement(ContratoTipo::values()),
            'status' => fake()->randomElement(ContratoStatus::values()),
            'observacoes' => fake()->optional()->paragraph(),
        ];
    }
}
