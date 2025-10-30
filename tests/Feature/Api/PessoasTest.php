<?php

namespace Tests\Feature\Api;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PessoasTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPessoaApiUser(array $permissions = []): User
    {
        $user = User::factory()->create([
            'permissoes' => $permissions,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_lists_pessoas_with_filters(): void
    {
        $this->actingAsPessoaApiUser(['pessoas.view']);

        Pessoa::factory()->count(3)->create();

        $response = $this->getJson('/api/pessoas');

        $response->assertOk()->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }

    public function test_creates_pessoa_normalizing_cpf_and_papeis(): void
    {
        $this->actingAsPessoaApiUser(['pessoas.create', 'pessoas.view']);

        $payload = [
            'nome_razao_social' => 'Maria Silva',
            'tipo_pessoa' => 'Fisica',
            'cpf_cnpj' => '123.456.789-09',
            'email' => 'maria@example.com',
            'telefone' => '(41) 99999-0000',
            'papeis' => ['proprietario', 'cliente', 'Proprietario'],
        ];

        $response = $this->postJson('/api/pessoas', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('pessoas', [
            'nome_razao_social' => 'Maria Silva',
            'cpf_cnpj' => '12345678909',
            'tipo_pessoa' => 'Fisica',
        ]);

        $this->assertSame(['Proprietario', 'Cliente'], $response->json('data.papeis'));
    }

    public function test_rejects_duplicate_cpf_cnpj(): void
    {
        $this->actingAsPessoaApiUser(['pessoas.create']);

        Pessoa::factory()->create([
            'cpf_cnpj' => '12345678909',
            'tipo_pessoa' => 'Fisica',
        ]);

        $payload = [
            'nome_razao_social' => 'Joao Souza',
            'tipo_pessoa' => 'Fisica',
            'cpf_cnpj' => '123.456.789-09',
        ];

        $response = $this->postJson('/api/pessoas', $payload);

        $response->assertStatus(422);
    }
}
