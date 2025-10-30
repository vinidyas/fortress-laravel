<?php

namespace Tests\Feature\Console;

use App\Console\Commands\GenerateInvoices;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Imovel;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_invoices_via_command_without_duplicating(): void
    {
        $imovel = Imovel::factory()->create([
            'valor_condominio' => 200,
            'valor_iptu' => 100,
            'condominio_isento' => false,
            'iptu_isento' => false,
        ]);

        $locador = Pessoa::factory()->create();
        $locatario = Pessoa::factory()->create();

        $contrato = Contrato::factory()->create([
            'imovel_id' => $imovel->id,
            'locador_id' => $locador->id,
            'locatario_id' => $locatario->id,
            'status' => 'Ativo',
            'dia_vencimento' => 5,
            'valor_aluguel' => 1500,
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'data_fim' => null,
        ]);

        Artisan::call(GenerateInvoices::class, ['--competencia' => now()->format('Y-m')]);

        $this->assertTrue(
            Fatura::query()
                ->where('contrato_id', $contrato->id)
                ->whereDate('competencia', now()->startOfMonth()->toDateString())
                ->exists(),
            'Fatura esperada nao foi gerada.'
        );

        Artisan::call(GenerateInvoices::class, ['--competencia' => now()->format('Y-m')]);

        $this->assertDatabaseCount('faturas', 1);
    }
}
