<?php

namespace Tests\Feature\Reports;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportPessoasTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumo_por_tipo_e_papel(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.pessoas']]);
        Sanctum::actingAs($user);

        Pessoa::factory()->create(['tipo_pessoa' => 'Fisica', 'papeis' => ['Proprietario']]);
        Pessoa::factory()->create(['tipo_pessoa' => 'Juridica', 'papeis' => ['Fornecedor']]);

        $response = $this->getJson('/api/reports/pessoas?papel=Proprietario');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals(1, $response->json('por_tipo.Fisica'));
        $this->assertCount(1, $response->json('amostra'));
    }

    public function test_export_csv(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.pessoas', 'reports.export']]);
        Sanctum::actingAs($user);

        Pessoa::factory()->count(2)->create(['tipo_pessoa' => 'Fisica']);

        $response = $this->get('/api/reports/pessoas/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Nome', $response->streamedContent());
    }
}
