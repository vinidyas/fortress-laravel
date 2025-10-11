<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'pessoa.updated',
            'auditable_type' => 'App\\Models\\Pessoa',
            'auditable_id' => $this->faker->randomNumber(),
            'payload' => [
                'before' => ['nome' => 'Antes'],
                'after' => ['nome' => 'Depois'],
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
