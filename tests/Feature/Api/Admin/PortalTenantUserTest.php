<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalTenantUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'permissoes' => ['admin.access'],
        ], $attributes));
    }

    public function test_admin_can_list_portal_tenants(): void
    {
        $admin = $this->makeAdmin();

        $comAcesso = Pessoa::factory()->create([
            'nome_razao_social' => 'João Locatário',
            'email' => 'joao@example.com',
            'papeis' => ['Locatario'],
        ]);

        User::factory()->create([
            'pessoa_id' => $comAcesso->id,
            'email' => 'joao@example.com',
            'username' => 'joao@example.com',
            'permissoes' => ['portal.access'],
        ]);

        $semAcesso = Pessoa::factory()->create([
            'nome_razao_social' => 'Maria Sem Portal',
            'email' => 'maria@example.com',
            'papeis' => ['Locatario'],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/portal/locatarios');

        $response->assertOk()->assertJsonFragment([
            'id' => $comAcesso->id,
            'nome' => 'João Locatário',
            'has_portal_access' => true,
        ])->assertJsonFragment([
            'id' => $semAcesso->id,
            'nome' => 'Maria Sem Portal',
            'has_portal_access' => false,
        ]);
    }

    public function test_portal_tenant_index_requires_admin(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/portal/locatarios')->assertForbidden();
    }

    public function test_admin_can_create_portal_tenant_user(): void
    {
        $admin = $this->makeAdmin();
        $pessoa = Pessoa::factory()->create([
            'papeis' => ['Locatario'],
        ]);

        Sanctum::actingAs($admin);

        Password::shouldReceive('broker')->once()->andReturnSelf();
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'locatario@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        $response = $this->postJson('/api/admin/portal/tenant-users', [
            'pessoa_id' => $pessoa->id,
            'email' => 'locatario@example.com',
        ]);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'email' => 'locatario@example.com',
                'pessoa_id' => $pessoa->id,
            ]);

        $this->assertDatabaseHas('usuarios', [
            'pessoa_id' => $pessoa->id,
            'email' => 'locatario@example.com',
        ]);
    }

    public function test_requires_person_with_locatario_role(): void
    {
        $admin = $this->makeAdmin();
        $pessoa = Pessoa::factory()->create([
            'papeis' => ['Cliente'],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/portal/tenant-users', [
            'pessoa_id' => $pessoa->id,
            'email' => 'locatario@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_forbids_non_admin_user(): void
    {
        $user = User::factory()->create();
        $pessoa = Pessoa::factory()->create([
            'papeis' => ['Locatario'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/portal/tenant-users', [
            'pessoa_id' => $pessoa->id,
            'email' => 'locatario@example.com',
        ]);

        $response->assertForbidden();
    }
}
