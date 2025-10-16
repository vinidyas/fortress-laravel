<?php

namespace Tests\Feature\Api;

use App\Models\Condominio;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_allows_uploading_attachments_when_creating_imovel(): void
    {
        Storage::fake('public');

        $user = $this->actingAsApiUser(['imoveis.create', 'imoveis.view']);

        $proprietario = Pessoa::factory()->create();
        $agenciador = Pessoa::factory()->create();
        $responsavel = Pessoa::factory()->create();
        $condominio = Condominio::factory()->create();

        $file = UploadedFile::fake()->create('matricula.pdf', 200, 'application/pdf');

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
            'anexos' => [$file],
            'anexos_legendas' => ['Matricula atualizada'],
        ];

        $response = $this->post('/api/imoveis', $payload);

        $response->assertCreated();

        $imovelId = $response->json('data.id');
        $this->assertNotNull($imovelId);

        $imovel = Imovel::with('anexos')->findOrFail($imovelId);
        $this->assertCount(1, $imovel->anexos);

        $attachment = $imovel->anexos->first();
        $this->assertSame('Matricula atualizada', $attachment->display_name);
        $this->assertSame('matricula.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame($user->id, $attachment->uploaded_by);
        Storage::disk('public')->assertExists($attachment->path);
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

    public function test_can_update_and_manage_imovel_attachments(): void
    {
        Storage::fake('public');

        $user = $this->actingAsApiUser(['imoveis.update', 'imoveis.view']);

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

        $keepAttachment = $imovel->anexos()->create([
            'path' => "imoveis/{$imovel->id}/contrato-antigo.pdf",
            'original_name' => 'contrato-antigo.pdf',
            'display_name' => 'Contrato antigo',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('public')->put($keepAttachment->path, 'dummy');

        $removeAttachment = $imovel->anexos()->create([
            'path' => "imoveis/{$imovel->id}/laudo-vencido.jpg",
            'original_name' => 'laudo-vencido.jpg',
            'display_name' => 'Laudo vencido',
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('public')->put($removeAttachment->path, 'dummy');

        $newFile = UploadedFile::fake()->create('vistoria.jpg', 80, 'image/jpeg');

        $payload = [
            '_method' => 'PUT',
            'codigo' => $imovel->codigo,
            'proprietario_id' => $proprietario->id,
            'agenciador_id' => $agenciador->id,
            'responsavel_id' => $responsavel->id,
            'tipo_imovel' => 'Apartamento',
            'finalidade' => ['Locacao'],
            'disponibilidade' => 'Disponivel',
            'cep' => '01001000',
            'estado' => 'SP',
            'cidade' => 'Sao Paulo',
            'bairro' => 'Centro',
            'rua' => 'Rua das Flores',
            'condominio_id' => $condominio->id,
            'logradouro' => 'Residencial Flores',
            'numero' => '456',
            'complemento' => 'Ap 34',
            'valor_locacao' => '2000.00',
            'valor_condominio' => '150.00',
            'condominio_isento' => false,
            'valor_iptu' => '80.00',
            'iptu_isento' => false,
            'outros_valores' => '0.00',
            'outros_isento' => true,
            'periodo_iptu' => 'Mensal',
            'dormitorios' => 2,
            'suites' => 1,
            'banheiros' => 2,
            'vagas_garagem' => 1,
            'area_total' => '90.00',
            'area_construida' => '85.00',
            'comodidades' => ['Piscina'],
            'anexos' => [$newFile],
            'anexos_legendas' => ['Laudo de vistoria'],
            'anexos_legendas_existentes' => [
                $keepAttachment->id => 'Contrato atualizado',
            ],
            'anexos_remover' => [$removeAttachment->id],
        ];

        $response = $this->post("/api/imoveis/{$imovel->id}", $payload);

        $response->assertOk();

        $imovel->refresh()->load('anexos');

        $this->assertDatabaseMissing('imovel_anexos', ['id' => $removeAttachment->id]);
        Storage::disk('public')->assertMissing($removeAttachment->path);

        $updatedAttachment = $imovel->anexos->firstWhere('id', $keepAttachment->id);
        $this->assertNotNull($updatedAttachment);
        $this->assertSame('Contrato atualizado', $updatedAttachment->display_name);

        $newAttachment = $imovel->anexos->firstWhere('id', '!=', $keepAttachment->id);
        $this->assertNotNull($newAttachment);
        $this->assertSame('vistoria.jpg', $newAttachment->original_name);
        $this->assertSame('Laudo de vistoria', $newAttachment->display_name);
        $this->assertSame('image/jpeg', $newAttachment->mime_type);
        $this->assertSame($user->id, $newAttachment->uploaded_by);
        Storage::disk('public')->assertExists($newAttachment->path);
    }
}
