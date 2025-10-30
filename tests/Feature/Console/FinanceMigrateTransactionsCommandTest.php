<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\FinanceMigrateTransactions;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FinanceMigrateTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--ansi' => false]);
    }

    public function test_runs_in_dry_run_mode_without_errors(): void
    {
        $account = FinancialAccount::factory()->create();

        FinancialTransaction::factory()->create([
            'account_id' => $account->id,
            'tipo' => 'debito',
            'valor' => 150,
            'data_ocorrencia' => '2025-01-10',
            'descricao' => 'Teste migração',
            'status' => 'pendente',
        ]);

        $exitCode = Artisan::call(FinanceMigrateTransactions::class, ['--dry-run' => true, '--chunk' => 1]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('journal_entries', ['description_custom' => 'Teste migração']);
    }
}
