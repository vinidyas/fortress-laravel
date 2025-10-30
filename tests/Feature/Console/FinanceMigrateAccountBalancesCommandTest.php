<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceMigrateAccountBalancesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--ansi' => false]);
    }

    public function test_recalculates_paid_entries_and_transfers(): void
    {
        $origin = FinancialAccount::factory()->create([
            'saldo_inicial' => 100,
            'saldo_atual' => 0,
        ]);

        $destination = FinancialAccount::factory()->create([
            'saldo_inicial' => 200,
            'saldo_atual' => 0,
        ]);

        JournalEntry::factory()->for($origin, 'bankAccount')->create([
            'type' => 'receita',
            'status' => 'pago',
            'amount' => 100,
        ]);

        JournalEntry::factory()->for($origin, 'bankAccount')->create([
            'type' => 'despesa',
            'status' => 'pago',
            'amount' => 30,
        ]);

        JournalEntry::factory()->for($origin, 'bankAccount')->create([
            'type' => 'receita',
            'status' => 'pendente',
            'amount' => 50,
        ]);

        JournalEntry::factory()->for($origin, 'bankAccount')->create([
            'type' => 'transferencia',
            'status' => 'pago',
            'amount' => 40,
            'counter_bank_account_id' => $destination->id,
        ]);

        $this->artisan('finance:migrate-account-balances');

        $this->assertSame(130.0, (float) $origin->fresh()->saldo_atual);
        $this->assertSame(240.0, (float) $destination->fresh()->saldo_atual);
    }
}
