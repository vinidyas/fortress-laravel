<?php

namespace Tests\Feature\Financeiro;

use App\Models\FinancialAccount;
use App\Models\FinancialReconciliation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReconciliationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--ansi' => false]);
    }

    private function actingAsUser(array $permissions): User
    {
        $user = User::factory()->create(['permissoes' => $permissions]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_export_requires_permission(): void
    {
        $this->actingAsUser([]);

        $response = $this->get('/api/financeiro/reconciliations/export');

        $response->assertForbidden();
    }

    public function test_export_returns_csv(): void
    {
        $this->actingAsUser(['financeiro.view']);

        $account = FinancialAccount::factory()->create();

        FinancialReconciliation::query()->create([
            'financial_account_id' => $account->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'opening_balance' => 1000,
            'closing_balance' => 1200,
            'status' => 'fechado',
            'notes' => null,
            'locked_by' => null,
        ]);

        $response = $this->get('/api/financeiro/reconciliations/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString((string) $account->id, $csv);
    }
}
