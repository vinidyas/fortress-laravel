<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        $movement = Carbon::instance($this->faker->dateTimeBetween('-1 month', 'now'));
        $due = (clone $movement)->addDays($this->faker->numberBetween(0, 15));

        return [
            'type' => $this->faker->randomElement(['receita', 'despesa']),
            'bank_account_id' => FinancialAccount::factory(),
            'counter_bank_account_id' => null,
            'cost_center_id' => null,
            'property_id' => null,
            'person_id' => null,
            'description_id' => null,
            'description_custom' => $this->faker->sentence(4),
            'notes' => $this->faker->optional()->sentence(),
            'reference_code' => $this->faker->optional()->regexify('[A-Z0-9]{6}'),
            'origin' => 'manual',
            'clone_of_id' => null,
            'movement_date' => $movement->toDateString(),
            'due_date' => $due->toDateString(),
            'payment_date' => null,
            'amount' => $this->faker->randomFloat(2, 100, 2000),
            'currency' => 'BRL',
            'status' => 'planejado',
            'installments_count' => 1,
            'paid_installments' => 0,
            'attachments_count' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (JournalEntry $entry) {
            $status = in_array($entry->status, ['planejado', 'pendente', 'pago', 'cancelado', 'atrasado'], true)
                ? $entry->status
                : 'planejado';

            $paymentDate = $status === 'pago'
                ? $entry->payment_date
                    ?? $entry->due_date
                    ?? $entry->movement_date
                : null;

            $entry->installments()->create([
                'numero_parcela' => 1,
                'movement_date' => $entry->movement_date,
                'due_date' => $entry->due_date ?? $entry->movement_date,
                'payment_date' => $paymentDate,
                'valor_principal' => $entry->amount,
                'valor_juros' => 0,
                'valor_multa' => 0,
                'valor_desconto' => 0,
                'valor_total' => $entry->amount,
                'status' => $status,
                'meta' => null,
            ]);
        });
    }
}
