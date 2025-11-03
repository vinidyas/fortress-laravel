<?php

namespace Tests\Feature\Auth;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalLoginTest extends TestCase
{
    use RefreshDatabase;

    private function portalHost(): string
    {
        return config('app.portal_domain', 'portal.fortressempreendimentos.com.br');
    }

    /** @test */
    public function locatario_can_login_using_email_and_password(): void
    {
        $pessoa = Pessoa::factory()->create([
            'tipo_pessoa' => 'Fisica',
            'email' => 'tenant@example.com',
            'papeis' => ['Locatario'],
        ]);

        $user = User::factory()->create([
            'pessoa_id' => $pessoa->id,
            'email' => 'tenant@example.com',
            'username' => 'tenant@example.com',
            'password' => Hash::make('secret123'),
            'permissoes' => ['portal.access'],
        ]);

        config()->set('app.portal_domain', $this->portalHost());

        $response = $this->post('https://'.$this->portalHost().'/login', [
            'email' => 'tenant@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('https://'.$this->portalHost().'/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function invalid_credentials_are_rejected_on_portal_login(): void
    {
        $pessoa = Pessoa::factory()->create([
            'tipo_pessoa' => 'Fisica',
            'email' => 'tenant@example.com',
            'papeis' => ['Locatario'],
        ]);

        User::factory()->create([
            'pessoa_id' => $pessoa->id,
            'email' => 'tenant@example.com',
            'username' => 'tenant@example.com',
            'password' => Hash::make('secret123'),
            'permissoes' => ['portal.access'],
        ]);

        config()->set('app.portal_domain', $this->portalHost());

        $response = $this->post('https://'.$this->portalHost().'/login', [
            'email' => 'tenant@example.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
