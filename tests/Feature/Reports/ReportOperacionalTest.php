<?php

namespace Tests\Feature\Reports;

use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportOperacionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocupacao_e_contratos_vencendo(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.operacional']]);
        Sanctum::actingAs($user);

        $imovelDisponivel = Imovel::factory()->create(['disponibilidade' => 'Disponivel']);
        $imovelIndisponivel = Imovel::factory()->create(['disponibilidade' => 'Indisponivel']);

        Contrato::factory()->create([
            'imovel_id' => $imovelIndisponivel->id,
            'status' => 'Ativo',
            'data_inicio' => now()->subMonths(6)->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->getJson('/api/reports/operacional?ate='.now()->addDays(30)->toDateString());

        $response->assertOk();
        $ocupacao = $response->json('ocupacao');
        $this->assertEquals(2, $ocupacao['total']);
        $this->assertEquals(1, $ocupacao['disponiveis']);
        $this->assertEquals(1, $ocupacao['indisponiveis']);
        $this->assertNotEmpty($response->json('contratos_vencendo'));
    }

    public function test_filtra_por_status_contrato(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.operacional']]);
        Sanctum::actingAs($user);

        Contrato::factory()->create([
            'status' => 'Suspenso',
            'data_inicio' => now()->subMonths(3)->toDateString(),
            'data_fim' => now()->addDays(7)->toDateString(),
        ]);

        Contrato::factory()->create([
            'status' => 'Ativo',
            'data_inicio' => now()->subMonths(3)->toDateString(),
            'data_fim' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->getJson('/api/reports/operacional?ate='.now()->addDays(30)->toDateString().'&status_contrato=Suspenso');

        $response->assertOk();
        $this->assertCount(1, $response->json('contratos_vencendo'));
        $this->assertSame('Suspenso', $response->json('contratos_vencendo.0.status'));
    }

    public function test_export_csv(): void
    {
        $user = User::factory()->create(['permissoes' => ['reports.view.operacional', 'reports.export']]);
        Sanctum::actingAs($user);

        Contrato::factory()->create([
            'status' => 'Ativo',
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'data_fim' => now()->addDays(15)->toDateString(),
        ]);

        $response = $this->get('/api/reports/operacional/export?ate='.now()->addDays(30)->toDateString());

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Contrato', $response->streamedContent());
    }
}
