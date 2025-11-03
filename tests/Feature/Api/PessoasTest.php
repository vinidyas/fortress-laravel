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

    public function test_requires_boleto_fields_for_locatario_even_with_string_payload(): void
    {
        $this->actingAsPessoaApiUser(['pessoas.create']);

        $payload = [
            'nome_razao_social' => 'Locatario Sem Dados',
            'tipo_pessoa' => 'Fisica',
            'papeis' => '["Locatario"]',
        ];

        $response = $this->postJson('/api/pessoas', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'cpf_cnpj',
            'email',
            'telefone',
            'cep',
            'estado',
            'cidade',
            'bairro',
            'rua',
            'numero',
        ]);
    }

    public function test_accepts_locatario_with_masked_fields(): void
    {
        $this->actingAsPessoaApiUser(['pessoas.create', 'pessoas.view']);

        $payload = [
            'nome_razao_social' => 'Joana Alves',
            'tipo_pessoa' => 'Fisica',
            'cpf_cnpj' => '123.456.789-09',
            'email' => 'joana@example.com',
            'telefone' => '(11) 91234-5678',
            'cep' => '01001-000',
            'estado' => 'sp',
            'cidade' => 'São Paulo',
            'bairro' => 'Centro',
            'rua' => 'Rua das Flores',
            'numero' => '123A',
            'papeis' => ['Locatario'],
        ];

        $response = $this->postJson('/api/pessoas', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('pessoas', [
            'nome_razao_social' => 'Joana Alves',
            'cpf_cnpj' => '12345678909',
            'cep' => '01001000',
            'telefone' => '11912345678',
            'estado' => 'SP',
        ]);
    }
}
