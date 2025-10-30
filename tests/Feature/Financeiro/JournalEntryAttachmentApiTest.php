<?php

namespace Tests\Feature\Financeiro;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalEntryAttachmentApiTest extends TestCase
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

    public function test_upload_list_download_and_delete_attachment(): void
    {
        Storage::fake('local');
        $this->actingAsUser(['financeiro.update', 'financeiro.view']);

        $account = FinancialAccount::factory()->create();
        $entry = JournalEntry::factory()->for($account, 'bankAccount')->create();

        $file = UploadedFile::fake()->create('comprovante.pdf', 120, 'application/pdf');

        $response = $this->postJson(
            "/api/financeiro/journal-entries/{$entry->id}/attachments",
            [
                'file' => $file,
            ]
        );

        $response->assertCreated();
        $attachmentId = $response->json('data.id');
        $this->assertNotNull($attachmentId);

        $entry->refresh();
        $attachment = $entry->attachments()->first();
        $this->assertNotNull($attachment);
        $this->assertTrue(Storage::disk('local')->exists($attachment->file_path));

        $list = $this->getJson("/api/financeiro/journal-entries/{$entry->id}/attachments");
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));

        $download = $this->get("/api/financeiro/journal-entries/{$entry->id}/attachments/{$attachmentId}/download");
        $download->assertOk();

        $delete = $this->deleteJson("/api/financeiro/journal-entries/{$entry->id}/attachments/{$attachmentId}");
        $delete->assertNoContent();

        $this->assertCount(0, $entry->attachments()->get());
        $this->assertFalse(Storage::disk('local')->exists($attachment->file_path));
    }
}
