<?php

namespace Tests\Feature\Financeiro;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalEntryReceiptApiTest extends TestCase
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

    public function test_generate_receipt_creates_pdf(): void
    {
        Storage::fake('local');
        $this->actingAsUser(['financeiro.view']);

        $account = FinancialAccount::factory()->create();
        /** @var JournalEntry $entry */
        $entry = JournalEntry::factory()->for($account, 'bankAccount')->create([
            'status' => 'pago',
        ]);

        /** @var JournalEntryInstallment $installment */
        $installment = $entry->installments()->first();
        $installment->update(['status' => 'pago']);

        $response = $this->postJson(
            "/api/financeiro/journal-entries/{$entry->id}/generate-receipt",
            [
                'installment_id' => $installment->id,
            ]
        );

        $response->assertCreated();
        $receiptId = $response->json('data.id');
        $this->assertNotNull($receiptId);

        $entry->refresh();
        $receipt = $entry->receipts()->first();
        $this->assertNotNull($receipt);
        $this->assertTrue(Storage::disk('local')->exists($receipt->pdf_path));
    }
}
