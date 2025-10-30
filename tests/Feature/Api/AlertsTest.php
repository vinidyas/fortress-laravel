<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_dismiss_alerts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'keys' => [
                'contract:10:2025-12-01',
                'invoice:25:2025-11-01',
            ],
        ];

        $response = $this->postJson('/api/alerts/dismiss', $payload);

        $response->assertOk()->assertJson(['message' => 'Alertas marcados como lidos.']);

        $this->assertDatabaseHas('user_alerts', [
            'user_id' => $user->id,
            'alert_key' => 'contract:10:2025-12-01',
        ]);

        $this->assertDatabaseHas('user_alerts', [
            'user_id' => $user->id,
            'alert_key' => 'invoice:25:2025-11-01',
        ]);

        $this->assertSame(2, UserAlert::count());
    }

    public function test_duplicate_alert_keys_are_not_duplicated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $keys = [
            'contract:99:2025-10-01',
            'invoice:50:2025-09-15',
        ];

        $this->postJson('/api/alerts/dismiss', ['keys' => $keys])->assertOk();
        $this->postJson('/api/alerts/dismiss', ['keys' => array_merge($keys, ['invoice:51:2025-09-20'])])->assertOk();

        $this->assertSame(3, UserAlert::count());
    }
}
