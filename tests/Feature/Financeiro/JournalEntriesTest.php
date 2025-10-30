<?php

namespace Tests\Feature\Financeiro;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalEntriesTest extends TestCase
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

    public function test_listagem_filtrada(): void
    {
        $this->actingAsUser(['financeiro.view']);

        $account = FinancialAccount::factory()->create();

        JournalEntry::factory()->count(2)->create([
            'bank_account_id' => $account->id,
            'type' => 'receita',
            'status' => 'pendente',
        ]);

        JournalEntry::factory()->create([
            'bank_account_id' => $account->id,
            'type' => 'despesa',
            'status' => 'pago',
        ]);

        $response = $this->getJson('/api/financeiro/journal-entries?filter[status]=pago');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_criacao_sem_permissao_retorna_403(): void
    {
        $this->actingAsUser([]);

        $account = FinancialAccount::factory()->create(['moeda' => 'BRL']);

        $payload = [
            'type' => 'despesa',
            'bank_account_id' => $account->id,
            'movement_date' => Carbon::today()->toDateString(),
            'amount' => 150,
            'installments' => [[
                'movement_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::today()->toDateString(),
                'valor_principal' => 150,
                'valor_total' => 150,
            ]],
        ];

        $response = $this->postJson('/api/financeiro/journal-entries', $payload);

        $response->assertForbidden();
    }

    public function test_cria_journal_entry(): void
    {
        $this->actingAsUser(['financeiro.create', 'financeiro.view']);

        $account = FinancialAccount::factory()->create(['moeda' => 'BRL']);

        $payload = [
            'type' => 'receita',
            'bank_account_id' => $account->id,
            'movement_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
            'amount' => 250.75,
            'description_custom' => 'Lançamento teste',
            'installments' => [[
                'movement_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::today()->addDays(5)->toDateString(),
                'valor_principal' => 250.75,
                'valor_total' => 250.75,
            ]],
        ];

        $response = $this->postJson('/api/financeiro/journal-entries', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('journal_entries', [
            'description_custom' => 'Lançamento teste',
            'amount' => 250.75,
        ]);
    }

    public function test_clone_journal_entry(): void
    {
        $this->actingAsUser(['financeiro.create', 'financeiro.view']);

        $entry = JournalEntry::factory()->create(['status' => 'pendente']);

        $response = $this->postJson("/api/financeiro/journal-entries/{$entry->id}/clone", [
            'movement_date' => Carbon::today()->addDay()->toDateString(),
        ]);

        $response->assertCreated();
        $cloneId = $response->json('data.id');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $cloneId,
            'clone_of_id' => $entry->id,
            'origin' => 'clonado',
        ]);
    }

    public function test_quita_parcela(): void
    {
        $this->actingAsUser(['financeiro.update', 'financeiro.view']);

        $entry = JournalEntry::factory()->create([
            'status' => 'pendente',
            'installments_count' => 1,
        ]);

        /** @var JournalEntryInstallment $installment */
        $installment = $entry->installments()->first();

        $response = $this->postJson(
            "/api/financeiro/journal-entries/{$entry->id}/installments/{$installment->id}/pay",
            [
                'payment_date' => Carbon::today()->toDateString(),
            ]
        );

        $response->assertOk();
        $this->assertDatabaseHas('journal_entry_installments', [
            'id' => $installment->id,
            'status' => 'pago',
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'status' => 'pago',
        ]);
    }

    public function test_export_sem_permissao(): void
    {
        $this->actingAsUser(['financeiro.view']);

        $response = $this->getJson('/api/financeiro/journal-entries/export');

        $response->assertForbidden();
    }

    public function test_export_sucesso(): void
    {
        $this->actingAsUser(['financeiro.view', 'financeiro.export']);

        JournalEntry::factory()->count(2)->create();

        $response = $this->get('/api/financeiro/journal-entries/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_generate_receipt_creates_pdf(): void
    {
        Storage::fake('local');
        $this->actingAsUser(['financeiro.view']);

        $entry = JournalEntry::factory()->create(['status' => 'pago']);

        $response = $this->postJson("/api/financeiro/journal-entries/{$entry->id}/generate-receipt");

        $response->assertCreated();
        $receiptId = $response->json('data.id');
        $this->assertNotNull($receiptId);

        $entry->refresh();
        $receipt = $entry->receipts()->first();
        $this->assertNotNull($receipt);
        $this->assertTrue(Storage::disk('local')->exists($receipt->pdf_path));
    }

    public function test_cancel_journal_entry(): void
    {
        $this->actingAsUser(['financeiro.update', 'financeiro.view']);

        $entry = JournalEntry::factory()->create(['status' => 'pendente']);

        $response = $this->postJson("/api/financeiro/journal-entries/{$entry->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'status' => 'cancelado',
        ]);
    }
}
