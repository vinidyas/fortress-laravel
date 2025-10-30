<?php

namespace Tests\Feature\Api;

use App\Mail\FaturaInvoiceMail;
use App\Models\Fatura;
use App\Models\FaturaAnexo;
use App\Models\FaturaEmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaturaAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create([
            'permissoes' => $permissions,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_user_can_upload_attachments_to_fatura(): void
    {
        Storage::fake('public');

        $this->actingAsUserWithPermissions(['faturas.update']);

        $fatura = Fatura::factory()->create();

        $file = UploadedFile::fake()->create('comprovante.pdf', 512, 'application/pdf');

        $response = $this->postJson("/api/faturas/{$fatura->id}/attachments", [
            'attachments' => [$file],
        ]);

        $response->assertCreated();

        $attachmentId = $response->json('data.0.id');
        $this->assertNotNull($attachmentId);

        $attachment = FaturaAnexo::find($attachmentId);
        $this->assertNotNull($attachment);
        $this->assertSame('comprovante.pdf', $attachment->original_name);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_user_can_remove_attachment_from_fatura(): void
    {
        Storage::fake('public');

        $this->actingAsUserWithPermissions(['faturas.update']);

        $fatura = Fatura::factory()->create();

        $path = "faturas/{$fatura->id}/documento.pdf";
        Storage::disk('public')->put($path, 'conteudo');

        $attachment = $fatura->anexos()->create([
            'path' => $path,
            'original_name' => 'documento.pdf',
            'display_name' => 'Documento',
            'mime_type' => 'application/pdf',
            'size' => 9,
            'uploaded_by' => null,
        ]);

        $this->assertSame($fatura->id, $attachment->fatura_id);
        $this->assertDatabaseHas('fatura_anexos', [
            'id' => $attachment->id,
        ]);
        $this->assertTrue($fatura->anexos()->whereKey($attachment->id)->exists());

        $response = $this->deleteJson("/api/faturas/{$fatura->id}/attachments/{$attachment->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('fatura_anexos', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_email_send_includes_selected_attachments_and_logs_them(): void
    {
        Storage::fake('public');
        Mail::fake();

        $this->actingAsUserWithPermissions(['faturas.email']);

        $fatura = Fatura::factory()->create();

        $path = "faturas/{$fatura->id}/recibo.pdf";
        Storage::disk('public')->put($path, 'conteudo-pdf');

        $attachment = $fatura->anexos()->create([
            'path' => $path,
            'original_name' => 'recibo.pdf',
            'display_name' => 'Recibo assinatura',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'uploaded_by' => null,
        ]);

        $payload = [
            'recipients' => ['destinatario@example.com'],
            'cc' => [],
            'bcc' => [],
            'attachments' => [$attachment->id],
            'message' => 'Segue fatura com comprovante.',
        ];

        $response = $this->postJson("/api/faturas/{$fatura->id}/email", $payload);

        $response->assertOk();

        Mail::assertSent(FaturaInvoiceMail::class, 1);

        $log = FaturaEmailLog::query()->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($fatura->id, $log->fatura_id);
        $this->assertIsArray($log->attachments);
        $this->assertCount(1, $log->attachments);
        $this->assertSame($attachment->id, $log->attachments[0]['id']);
        $this->assertSame('recibo.pdf', $log->attachments[0]['original_name']);
    }

    public function test_cannot_send_email_with_attachments_from_other_fatura(): void
    {
        Storage::fake('public');

        $this->actingAsUserWithPermissions(['faturas.email']);

        $faturaA = Fatura::factory()->create();
        $faturaB = Fatura::factory()->create();

        $path = "faturas/{$faturaB->id}/externo.pdf";
        Storage::disk('public')->put($path, 'externo');

        $foreignAttachment = $faturaB->anexos()->create([
            'path' => $path,
            'original_name' => 'externo.pdf',
            'display_name' => 'Anexo externo',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'uploaded_by' => null,
        ]);

        $response = $this->postJson("/api/faturas/{$faturaA->id}/email", [
            'recipients' => ['destinatario@example.com'],
            'attachments' => [$foreignAttachment->id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['attachments']);
    }

    public function test_can_apply_standard_name_to_attachment(): void
    {
        Storage::fake('public');

        $this->actingAsUserWithPermissions(['faturas.update']);

        $fatura = Fatura::factory()->create([
            'competencia' => '2024-10-01',
        ]);

        $fatura->load('contrato.imovel.condominio');

        $path = "faturas/{$fatura->id}/arquivo.pdf";
        Storage::disk('public')->put($path, 'conteudo');

        $attachment = $fatura->anexos()->create([
            'path' => $path,
            'original_name' => 'arquivo.pdf',
            'display_name' => 'arquivo.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1200,
            'uploaded_by' => null,
        ]);

        $response = $this->patchJson("/api/faturas/{$fatura->id}/attachments/{$attachment->id}/rename");

        $response->assertOk();

        $imovel = $fatura->contrato->imovel;
        $base = trim((string) ($imovel->condominio?->nome ?? ''));
        $fallback = $base !== '' ? $base : 'Sem condomínio';
        $complemento = trim((string) ($imovel->complemento ?? ''));
        $imovelLabel = $complemento !== '' ? sprintf('%s — %s', $fallback, $complemento) : $fallback;
        $expectedDisplay = sprintf(
            'Boleto - %s - %s - 10/24.pdf',
            $fatura->contrato->codigo_contrato ?? $fatura->contrato_id,
            $imovelLabel
        );

        $response->assertJsonPath('data.display_name', $expectedDisplay);

        $attachment->refresh();

        $this->assertSame($expectedDisplay, $attachment->display_name);
        $this->assertSame('arquivo.pdf', $attachment->original_name);

        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_can_restore_original_name(): void
    {
        Storage::fake('public');

        $this->actingAsUserWithPermissions(['faturas.update']);

        $fatura = Fatura::factory()->create([
            'competencia' => '2024-10-01',
        ]);

        $fatura->load('contrato.imovel.condominio');

        $path = "faturas/{$fatura->id}/arquivo.pdf";
        Storage::disk('public')->put($path, 'conteudo');

        $attachment = $fatura->anexos()->create([
            'path' => $path,
            'original_name' => 'arquivo.pdf',
            'display_name' => 'arquivo.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1200,
            'uploaded_by' => null,
        ]);

        $this->patchJson("/api/faturas/{$fatura->id}/attachments/{$attachment->id}/rename");

        $attachment->refresh();
        $this->assertNotSame('arquivo.pdf', $attachment->display_name);

        $response = $this->patchJson("/api/faturas/{$fatura->id}/attachments/{$attachment->id}/reset-name");

        $response->assertOk()->assertJsonPath('data.display_name', 'arquivo.pdf');

        $attachment->refresh();

        $this->assertSame('arquivo.pdf', $attachment->display_name);
        $this->assertSame('arquivo.pdf', $attachment->original_name);
        $this->assertSame("faturas/{$fatura->id}/arquivo.pdf", $attachment->path);

        Storage::disk('public')->assertExists($attachment->path);
    }
}
