<?php

namespace Tests\Feature\Api;

use App\Models\Condominio;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImoveisTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsApiUser(array $permissions = []): User
    {
        $user = User::factory()->create([
            'permissoes' => $permissions,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_lists_imoveis_with_filters(): void
    {
        $this->actingAsApiUser(['imoveis.view']);

        Imovel::factory()->count(2)->create();

        $response = $this->getJson('/api/imoveis');

        $response->assertOk()->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }

    public function test_creates_imovel_generating_codigo_when_not_provided(): void
    {
        $this->actingAsApiUser(['imoveis.create', 'imoveis.view']);

        $proprietario = Pessoa::factory()->create();
        $agenciador = Pessoa::factory()->create();
        $responsavel = Pessoa::factory()->create();
        $condominio = Condominio::factory()->create();

        $payload = [
            'codigo' => '',
            'proprietario_id' => $proprietario->id,
            'agenciador_id' => $agenciador->id,
            'responsavel_id' => $responsavel->id,
            'tipo_imovel' => 'Apartamento',
            'finalidade' => ['Locacao'],
            'disponibilidade' => 'Disponivel',
            'cep' => '80000000',
            'estado' => 'PR',
            'cidade' => 'Curitiba',
            'bairro' => 'Centro',
            'rua' => 'Rua Um',
            'condominio_id' => $condominio->id,
            'logradouro' => 'Logradouro Um',
            'numero' => '123',
            'complemento' => 'Apto 10',
            'valor_locacao' => '1500.00',
            'valor_condominio' => '200.00',
            'condominio_isento' => false,
            'valor_iptu' => '100.00',
            'iptu_isento' => false,
            'outros_valores' => '50.00',
            'outros_isento' => false,
            'periodo_iptu' => 'Mensal',
            'dormitorios' => 2,
            'suites' => 1,
            'banheiros' => 2,
            'vagas_garagem' => 1,
            'area_total' => '80.00',
            'area_construida' => '75.00',
            'comodidades' => ['Piscina'],
        ];

        $response = $this->postJson('/api/imoveis', $payload);

        $response->assertCreated();
        $this->assertDatabaseCount('imoveis', 1);

        $codigo = $response->json('data.codigo');
        $this->assertNotEmpty($codigo);
    }

    public function test_rejects_duplicate_codigo(): void
    {
        $this->actingAsApiUser(['imoveis.create']);

        $imovel = Imovel::factory()->create(['codigo' => '12345']);

        $proprietario = $imovel->proprietario ?? Pessoa::factory()->create();

        $payload = [
            'codigo' => '12345',
            'proprietario_id' => $proprietario->id,
            'tipo_imovel' => 'Casa',
            'finalidade' => ['Locacao'],
            'disponibilidade' => 'Disponivel',
            'numero' => '100',
            'periodo_iptu' => 'Mensal',
        ];

        $response = $this->postJson('/api/imoveis', $payload);

        $response->assertStatus(422);
    }

    public function test_updates_imovel_successfully(): void
    {
        $this->actingAsApiUser(['imoveis.update', 'imoveis.view']);

        $proprietario = Pessoa::factory()->create();
        $agenciador = Pessoa::factory()->create();
        $responsavel = Pessoa::factory()->create();
        $condominio = Condominio::factory()->create();

        $imovel = Imovel::factory()->create([
            'proprietario_id' => $proprietario->id,
            'agenciador_id' => $agenciador->id,
            'responsavel_id' => $responsavel->id,
            'condominio_id' => $condominio->id,
        ]);

        $payload = [
            'codigo' => $imovel->codigo,
            'proprietario_id' => $proprietario->id,
            'agenciador_id' => $agenciador->id,
            'responsavel_id' => $responsavel->id,
            'tipo_imovel' => 'Apartamento',
            'finalidade' => ['Venda'],
            'disponibilidade' => 'Indisponivel',
            'cep' => '01001000',
            'estado' => 'SP',
            'cidade' => 'Sao Paulo',
            'bairro' => 'Centro',
            'rua' => 'Rua Teste',
            'condominio_id' => $condominio->id,
            'logradouro' => 'Edificio Central',
            'numero' => '321',
            'complemento' => 'Ap 12',
            'valor_locacao' => '3500.00',
            'valor_condominio' => '250.00',
            'condominio_isento' => false,
            'valor_iptu' => '120.00',
            'iptu_isento' => false,
            'outros_valores' => '0.00',
            'outros_isento' => true,
            'periodo_iptu' => 'Mensal',
            'dormitorios' => 3,
            'suites' => 1,
            'banheiros' => 2,
            'vagas_garagem' => 1,
            'area_total' => '120.00',
            'area_construida' => '100.00',
            'comodidades' => ['Piscina', 'Academia'],
        ];

        $response = $this->putJson("/api/imoveis/{$imovel->id}", $payload);

        $response->assertOk();
        $response->assertJsonPath('data.disponibilidade', 'Indisponivel');
        $response->assertJsonPath('data.finalidade', ['Venda']);
        $response->assertJsonPath('data.enderecos.cidade', 'Sao Paulo');

        $this->assertDatabaseHas('imoveis', [
            'id' => $imovel->id,
            'cidade' => 'Sao Paulo',
            'disponibilidade' => 'Indisponivel',
            'numero' => '321',
        ]);
    }
}
