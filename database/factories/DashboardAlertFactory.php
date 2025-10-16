<?php

namespace Database\Factories;

use App\Models\DashboardAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class DashboardAlertFactory extends Factory
{
    protected $model = DashboardAlert::class;

    public function definition(): array
    {
        $occurredAt = $this->faker->dateTimeBetween('-10 days', 'now');

        return [
            'key' => $this->faker->unique()->uuid(),
            'category' => $this->faker->randomElement(['contract.expiring', 'invoice.overdue', 'invoice.due_soon']),
            'severity' => $this->faker->randomElement(['danger', 'warning', 'info']),
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->sentence(12),
            'resource_type' => null,
            'resource_id' => null,
            'payload' => [],
            'occurred_at' => $occurredAt,
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ];
    }

    public function resolved(): self
    {
        return $this->state(function () {
            $resolvedAt = $this->faker->dateTimeBetween('-5 days', 'now');

            return [
                'resolved_at' => $resolvedAt,
                'resolution_notes' => $this->faker->sentence(8),
            ];
        });
    }
}
