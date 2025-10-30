<?php

namespace Tests\Feature\Auditoria;

use App\Models\AuditLog;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_auditoria_para_usuario_autorizado(): void
    {
        $user = User::factory()->create(['permissoes' => ['auditoria.view']]);
        Sanctum::actingAs($user);

        AuditLog::factory()->count(2)->create();

        $response = $this->getJson('/api/auditoria');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
    }

    public function test_search_matches_payload_json(): void
    {
        $user = User::factory()->create(['permissoes' => ['auditoria.view']]);
        Sanctum::actingAs($user);

        AuditLog::factory()->create([
            'payload' => [
                'after' => ['descricao' => 'Atualizacao critica'],
            ],
        ]);

        $response = $this->getJson('/api/auditoria?search=critica');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_export_sem_permissao_retorna_403(): void
    {
        $user = User::factory()->create(['permissoes' => ['auditoria.view']]);
        Sanctum::actingAs($user);

        $response = $this->get('/api/auditoria/export');

        $response->assertForbidden();
    }

    public function test_export_com_permissao(): void
    {
        $user = User::factory()->create(['permissoes' => ['auditoria.view', 'auditoria.export']]);
        Sanctum::actingAs($user);

        $response = $this->get('/api/auditoria/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_export_json(): void
    {
        $user = User::factory()->create(['permissoes' => ['auditoria.view', 'auditoria.export']]);
        Sanctum::actingAs($user);

        AuditLog::factory()->create(['user_id' => $user->id, 'action' => 'teste']);

        $response = $this->get('/api/auditoria/export?format=json');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json; charset=UTF-8');

        $payload = json_decode($response->streamedContent(), true);
        $this->assertIsArray($payload['rows']);
        $this->assertNotEmpty($payload['rows']);
    }

    public function test_criacao_transacao_registra_auditoria(): void
    {
        $user = User::factory()->create([
            'permissoes' => ['financeiro.create', 'financeiro.view', 'auditoria.view'],
        ]);
        Sanctum::actingAs($user);

        AuditLog::query()->delete();
        $account = FinancialAccount::factory()->create();

        $this->postJson('/api/financeiro/journal-entries', [
            'type' => 'receita',
            'bank_account_id' => $account->id,
            'movement_date' => now()->toDateString(),
            'amount' => 123.45,
            'description_custom' => 'Entrada auditoria',
            'installments' => [[
                'movement_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'valor_principal' => 123.45,
                'valor_total' => 123.45,
                'status' => 'planejado',
            ]],
        ])->assertCreated();

        $this->assertTrue(
            AuditLog::query()->where('action', 'journal_entry.created')->exists(),
            'Registro de auditoria de criacao nao encontrado.'
        );
    }

    public function test_atualizacao_transacao_registra_auditoria(): void
    {
        $user = User::factory()->create([
            'permissoes' => ['financeiro.update', 'financeiro.view', 'auditoria.view'],
        ]);
        Sanctum::actingAs($user);

        /** @var JournalEntry $entry */
        $entry = JournalEntry::factory()->create([
            'status' => 'planejado',
            'description_custom' => 'Original',
        ]);
        AuditLog::query()->delete();

        $this->putJson("/api/financeiro/journal-entries/{$entry->id}", [
            'description_custom' => 'Ajuste auditoria',
        ])->assertOk();

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'journal_entry.updated')
                ->where('auditable_id', $entry->id)
                ->exists(),
            'Registro de auditoria de atualizacao nao encontrado.'
        );
    }
}
