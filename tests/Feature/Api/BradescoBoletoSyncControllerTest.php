<?php

namespace Tests\Feature\Api;

use App\Jobs\Bradesco\SyncPendingBradescoBoletos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BradescoBoletoSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(array $permissions = []): User
    {
        $user = User::factory()->create([
            'permissoes' => $permissions,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_requires_permission(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/bradesco/boletos/sync')->assertForbidden();
    }

    public function test_dispatches_job_when_authorized(): void
    {
        Queue::fake();
        $this->actingAsUser(['faturas.boleto.generate']);

        $this->postJson('/api/bradesco/boletos/sync', ['lote' => 25])
            ->assertOk()
            ->assertJsonPath('meta.message', 'Sincronização dos boletos foi enfileirada.');

        Queue::assertPushed(SyncPendingBradescoBoletos::class);
    }
}
