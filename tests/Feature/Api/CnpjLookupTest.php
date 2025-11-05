<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CnpjLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cnpj_lookup.cache_ttl' => 0,
            'services.cnpj_lookup.providers' => ['brasilapi'],
            'services.cnpj_lookup.brasilapi.base_url' => 'https://brasilapi.com.br/api',
        ]);
    }

    private function authenticate(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
    }

    public function test_fetches_cnpj_data_from_brasilapi(): void
    {
        $this->authenticate();

        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/*' => Http::response([
                'cnpj' => '12345678000195',
                'razao_social' => 'ACME COMERCIO DE TESTES LTDA',
                'nome_fantasia' => 'ACME TESTES',
                'email' => 'contato@acme.com.br',
                'telefone' => '1130000000',
                'endereco' => [
                    'cep' => '01001000',
                    'uf' => 'SP',
                    'municipio' => 'São Paulo',
                    'bairro' => 'Sé',
                    'logradouro' => 'Praça da Sé',
                    'numero' => '100',
                    'complemento' => 'Conjunto 10',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/cnpj/12345678000195');

        $response->assertOk()->assertJson([
            'data' => [
                'cnpj' => '12345678000195',
                'razao_social' => 'ACME COMERCIO DE TESTES LTDA',
                'provider' => 'brasilapi',
                'cep' => '01001000',
                'uf' => 'SP',
                'municipio' => 'São Paulo',
            ],
        ]);

        Http::assertSentCount(1);
    }

    public function test_returns_404_when_provider_does_not_find_cnpj(): void
    {
        $this->authenticate();

        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/*' => Http::response(['message' => 'CNPJ não encontrado'], 404),
        ]);

        $response = $this->getJson('/api/cnpj/12345678000195');

        $response->assertStatus(404)->assertJsonStructure(['message', 'errors']);
    }

    public function test_requires_valid_cnpj(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/cnpj/123');

        $response->assertStatus(422)->assertJsonValidationErrors(['cnpj']);
    }
}
