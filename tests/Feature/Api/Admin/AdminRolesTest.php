<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    private function actingAsAdmin(): User
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();

        $user = User::factory()->create([
            'permissoes' => ['admin.access'],
        ]);

        if ($adminRole) {
            $user->syncRoles($adminRole);
        }

        $user->givePermissionTo('admin.access');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_can_list_roles(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/roles');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'permissions'],
            ],
            'permissions',
        ]);
    }

    public function test_non_admin_cannot_access_roles(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/roles');

        $response->assertForbidden();
    }

    public function test_admin_can_create_role(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'name' => 'Supervisores',
            'slug' => 'supervisores',
            'description' => 'Equipe de supervisão',
            'permissions' => ['imoveis.view', 'pessoas.view'],
        ];

        $response = $this->postJson('/api/admin/roles', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('roles', [
            'slug' => 'supervisores',
            'description' => 'Equipe de supervisão',
        ]);
    }

    public function test_admin_role_always_keeps_admin_access_permission(): void
    {
        $this->actingAsAdmin();

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $payload = [
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => 'Acesso completo ao sistema',
            'permissions' => ['imoveis.view'],
        ];

        $response = $this->putJson("/api/admin/roles/{$adminRole->id}", $payload);

        $response->assertOk();
        $adminRole->refresh();

        $this->assertTrue($adminRole->hasPermissionTo('admin.access'));
        $this->assertTrue($adminRole->hasPermissionTo('imoveis.view'));
    }
}
