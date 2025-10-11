<?php

namespace Tests\Feature\Financeiro;

use App\Models\Contrato;
use App\Models\CostCenter;
use App\Models\Fatura;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialTransactionsTest extends TestCase
{
    use RefreshDatabase;

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
        FinancialTransaction::factory()->count(2)->create(['account_id' => $account->id, 'status' => 'pendente']);
        FinancialTransaction::factory()->create(['account_id' => $account->id, 'status' => 'conciliado']);

        $response = $this->getJson('/api/financeiro/transactions?filter[status]=conciliado');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_criacao_sem_permissao_retorna_403(): void
    {
        $this->actingAsUser([]);

        $account = FinancialAccount::factory()->create();

        $response = $this->postJson('/api/financeiro/transactions', [
            'account_id' => $account->id,
            'tipo' => 'debito',
            'valor' => 100,
            'data_ocorrencia' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_criacao_sucesso(): void
    {
        $this->actingAsUser(['financeiro.create', 'financeiro.view']);
        $account = FinancialAccount::factory()->create();
        $costCenter = CostCenter::factory()->create();

        $response = $this->postJson('/api/financeiro/transactions', [
            'account_id' => $account->id,
            'cost_center_id' => $costCenter->id,
            'tipo' => 'credito',
            'valor' => 150,
            'data_ocorrencia' => now()->toDateString(),
            'descricao' => 'repasse aluguel',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('financial_transactions', ['descricao' => 'repasse aluguel']);
    }

    public function test_conciliacao_exige_permissao_especifica(): void
    {
        $this->actingAsUser(['financeiro.view']);
        $transaction = FinancialTransaction::factory()->create(['status' => 'pendente']);

        $response = $this->postJson("/api/financeiro/transactions/{$transaction->id}/reconcile", [
            'valor_conciliado' => $transaction->valor,
        ]);

        $response->assertForbidden();
    }

    public function test_conciliacao_sucesso(): void
    {
        $this->actingAsUser(['financeiro.view', 'financeiro.reconcile']);
        $transaction = FinancialTransaction::factory()->create(['status' => 'pendente']);

        $response = $this->postJson("/api/financeiro/transactions/{$transaction->id}/reconcile", [
            'valor_conciliado' => $transaction->valor,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('financial_transactions', ['id' => $transaction->id, 'status' => 'conciliado']);
    }

    public function test_nao_concilia_transacao_nao_pendente(): void
    {
        $this->actingAsUser(['financeiro.view', 'financeiro.reconcile']);

        $transaction = FinancialTransaction::factory()->create(['status' => 'cancelado']);

        $response = $this->postJson("/api/financeiro/transactions/{$transaction->id}/reconcile", [
            'valor_conciliado' => $transaction->valor,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'status' => 'cancelado',
        ]);
    }

    public function test_export_sem_permissao_bloqueia(): void
    {
        $this->actingAsUser(['financeiro.view']);

        $response = $this->getJson('/api/financeiro/transactions/export');

        $response->assertForbidden();
    }

    public function test_atualiza_transacao(): void
    {
        $this->actingAsUser(['financeiro.update', 'financeiro.view']);

        $transaction = FinancialTransaction::factory()->create([
            'status' => 'pendente',
            'descricao' => 'original',
        ]);
        $newAccount = FinancialAccount::factory()->create();

        $payload = [
            'account_id' => $newAccount->id,
            'tipo' => 'debito',
            'valor' => 250.55,
            'data_ocorrencia' => now()->toDateString(),
            'descricao' => 'atualizado',
        ];

        $response = $this->putJson("/api/financeiro/transactions/{$transaction->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'account_id' => $newAccount->id,
            'descricao' => 'atualizado',
            'tipo' => 'debito',
        ]);
    }

    public function test_nao_permite_alterar_status_via_update(): void
    {
        $this->actingAsUser(['financeiro.update', 'financeiro.view']);

        $transaction = FinancialTransaction::factory()->create(['status' => 'pendente']);

        $response = $this->putJson("/api/financeiro/transactions/{$transaction->id}", [
            'status' => 'conciliado',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'status' => 'pendente',
        ]);
    }

    public function test_cancelamento_transacao(): void
    {
        $this->actingAsUser(['financeiro.update', 'financeiro.view']);

        $transaction = FinancialTransaction::factory()->create(['status' => 'pendente']);

        $response = $this->postJson("/api/financeiro/transactions/{$transaction->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'status' => 'cancelado',
        ]);
    }

    public function test_criacao_com_vinculos_de_contrato_e_fatura(): void
    {
        $this->actingAsUser(['financeiro.create', 'financeiro.view']);

        $account = FinancialAccount::factory()->create();
        $contrato = Contrato::factory()->create(['status' => 'Ativo']);
        $fatura = Fatura::factory()->for($contrato)->create(['status' => 'Aberta']);

        $response = $this->postJson('/api/financeiro/transactions', [
            'account_id' => $account->id,
            'contrato_id' => $contrato->id,
            'fatura_id' => $fatura->id,
            'tipo' => 'credito',
            'valor' => 999.9,
            'data_ocorrencia' => now()->toDateString(),
            'descricao' => 'repasse contrato',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('financial_transactions', [
            'descricao' => 'repasse contrato',
            'contrato_id' => $contrato->id,
            'fatura_id' => $fatura->id,
        ]);
    }

    public function test_busca_considera_meta_json(): void
    {
        $this->actingAsUser(['financeiro.view']);

        $transaction = FinancialTransaction::factory()->create([
            'descricao' => null,
            'meta' => ['observacao' => 'repasse especial'],
        ]);

        $response = $this->getJson('/api/financeiro/transactions?filter[search]=especial');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($transaction->id));
    }
}
