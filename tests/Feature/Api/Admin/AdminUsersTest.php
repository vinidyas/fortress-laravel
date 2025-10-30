<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUsersTest extends TestCase
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

    public function test_admin_can_list_users(): void
    {
        $this->actingAsAdmin();

        User::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/users');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                '*' => ['id', 'username', 'nome', 'roles', 'ativo'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_non_admin_cannot_access_users_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAsAdmin();

        $operadorRole = Role::query()->where('slug', 'operador')->first();
        self::assertNotNull($operadorRole);

        $payload = [
            'nome' => 'Novo Usuário',
            'username' => 'novo.usuario',
            'password' => 'senhaSegura1',
            'password_confirmation' => 'senhaSegura1',
            'role_id' => $operadorRole->id,
            'roles' => [$operadorRole->id],
            'permissions' => ['imoveis.view'],
        ];

        $response = $this->postJson('/api/admin/users', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('usuarios', [
            'username' => 'novo.usuario',
            'nome' => 'Novo Usuário',
        ]);
    }

    public function test_admin_can_update_user_and_permissions(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create([
            'nome' => 'Teste',
            'username' => 'teste.user',
        ]);

        $gestorRole = Role::query()->create([
            'name' => 'Gestor',
            'slug' => 'gestor',
            'description' => 'Papel de testes',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $payload = [
            'nome' => 'Teste Atualizado',
            'username' => 'teste.user',
            'role_id' => $gestorRole->id,
            'roles' => [$gestorRole->id],
            'permissions' => ['imoveis.view', 'pessoas.view'],
            'ativo' => true,
        ];

        $response = $this->putJson("/api/admin/users/{$user->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('usuarios', [
            'id' => $user->id,
            'nome' => 'Teste Atualizado',
        ]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->actingAsAdmin();

        $primaryRoleId = $admin->role_id ?? $admin->roles->first()?->id ?? null;
        $roleIds = array_values(array_filter([$primaryRoleId], fn ($value) => $value !== null));

        $payload = [
            'nome' => $admin->nome,
            'username' => $admin->username,
            'role_id' => $primaryRoleId,
            'roles' => $roleIds,
            'permissions' => ['admin.access'],
            'ativo' => false,
        ];

        $response = $this->putJson("/api/admin/users/{$admin->id}", $payload);

        $response->assertStatus(422);
    }
}
