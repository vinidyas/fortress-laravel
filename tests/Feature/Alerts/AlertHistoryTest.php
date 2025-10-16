<?php

namespace Tests\Feature\Alerts;

use App\Models\Contrato;
use App\Models\DashboardAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlertHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_generates_alert_records(): void
    {
        $user = User::factory()->create([
            'permissoes' => ['alerts.view'],
        ]);

        $contract = Contrato::factory()->create([
            'status' => 'Ativo',
            'data_fim' => Carbon::now()->addDays(2),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk();

        $this->assertDatabaseHas('dashboard_alerts', [
            'category' => 'contract.expiring',
            'resource_type' => Contrato::class,
            'resource_id' => $contract->id,
        ]);
    }

    public function test_history_endpoint_requires_permission(): void
    {
        $user = User::factory()->create(['permissoes' => []]);

        Sanctum::actingAs($user);

        $this->getJson('/api/alerts/history')->assertForbidden();
    }

    public function test_history_endpoint_returns_alerts(): void
    {
        $alert = DashboardAlert::factory()->create(['category' => 'contract.expiring']);
        DashboardAlert::factory()->count(2)->create();
        $user = User::factory()->create(['permissoes' => ['alerts.view']]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/alerts/history');

        $response->assertOk()->assertJsonFragment([
            'category' => 'contract.expiring',
            'id' => $alert->id,
        ]);
    }

    public function test_resolve_endpoint_updates_alert(): void
    {
        $alert = DashboardAlert::factory()->create([
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $user = User::factory()->create(['permissoes' => ['alerts.view', 'alerts.resolve']]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/alerts/history/{$alert->id}/resolve", [
            'notes' => 'Tratado manualmente.',
        ]);

        $response->assertOk()->assertJsonFragment([
            'resolution_notes' => 'Tratado manualmente.',
        ]);

        $this->assertDatabaseHas('dashboard_alerts', [
            'id' => $alert->id,
            'resolved_by' => $user->id,
        ]);
    }

    public function test_history_page_renders_for_authorized_user(): void
    {
        DashboardAlert::factory()->create();
        $user = User::factory()->create(['permissoes' => ['alerts.view']]);

        $this->actingAs($user)
            ->get('/alertas/historico')
            ->assertOk();
    }
}
