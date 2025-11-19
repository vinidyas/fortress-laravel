<?php

namespace Tests\Feature\Portal;

use App\Enums\ContratoStatus;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Models\FaturaLancamento;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PortalEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $portalDomain = $this->portalHost();
        config()->set('app.portal_domain', $portalDomain);
        config()->set('app.url', 'https://'.$portalDomain);
        URL::forceRootUrl('https://'.$portalDomain);
    }

    protected function tearDown(): void
    {
        URL::forceRootUrl(null);

        parent::tearDown();
    }

    private function createTenantUser(): array
    {
        $locatario = Pessoa::factory()->create([
            'papeis' => ['Locatario'],
            'email' => 'tenant@example.com',
        ]);

        $user = User::factory()->create([
            'username' => 'tenant_user',
            'email' => 'tenant@example.com',
            'pessoa_id' => $locatario->id,
            'permissoes' => [],
        ]);

        return [$user, $locatario];
    }

    public function test_lists_contracts_for_tenant(): void
    {
        [$user, $locatario] = $this->createTenantUser();

        $contrato = Contrato::factory()->create([
            'locatario_id' => $locatario->id,
            'status' => ContratoStatus::Ativo->value,
            'valor_aluguel' => 1500,
        ]);

        // contrato de outro locatário não deve aparecer
        Contrato::factory()->create();

        $this->portalGetJson($user, 'portal.contracts.index')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $contrato->id,
                'codigo' => $contrato->codigo_contrato,
                'status' => ContratoStatus::Ativo->value,
            ]);
    }

    public function test_lists_invoices_for_tenant(): void
    {
        [$user, $locatario] = $this->createTenantUser();

        $contrato = Contrato::factory()->create([
            'locatario_id' => $locatario->id,
            'status' => ContratoStatus::Ativo->value,
        ]);

        $fatura = Fatura::factory()->create([
            'contrato_id' => $contrato->id,
            'valor_total' => 2000,
            'status' => 'Aberta',
        ]);

        $boleto = FaturaBoleto::create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => '1234567890',
            'nosso_numero' => '1234567890',
            'document_number' => '123456',
            'linha_digitavel' => '34191.79001 01043.510047 91020.150008 8 88880000010000',
            'codigo_barras' => '34198888800000100001790010143510049102015000',
            'valor' => 2000,
            'vencimento' => now()->addDays(5),
            'status' => FaturaBoleto::STATUS_REGISTERED,
            'registrado_em' => now(),
            'pdf_url' => 'https://example.com/boleto.pdf',
        ]);

        $this->portalGetJson($user, 'portal.invoices.index')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $fatura->id,
                'contrato_id' => $contrato->id,
                'valor_total' => 2000.0,
            ])
            ->assertJsonFragment([
                'nosso_numero' => '1234567890',
            ])
            ->assertJsonFragment([
                'pdf_download_url' => 'https://'.$this->portalHost().route('boletos.pdf', ['boleto' => $boleto->id], false),
            ]);
    }

    public function test_shows_invoice_details_for_tenant(): void
    {
        [$user, $locatario] = $this->createTenantUser();

        $contrato = Contrato::factory()->create([
            'locatario_id' => $locatario->id,
            'status' => ContratoStatus::Ativo->value,
        ]);

        $fatura = Fatura::factory()->create([
            'contrato_id' => $contrato->id,
            'valor_total' => 890,
            'status' => 'Aberta',
        ]);

        FaturaLancamento::factory()->create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Aluguel Mensal',
            'valor_total' => 890,
        ]);

        $boleto = FaturaBoleto::create([
            'fatura_id' => $fatura->id,
            'bank_code' => 'bradesco',
            'external_id' => '9876543210',
            'nosso_numero' => '9876543210',
            'document_number' => '654321',
            'linha_digitavel' => '34191.79001 01043.510047 91020.150008 8 88880000020000',
            'codigo_barras' => '34198888800000200001790010143510049102015001',
            'valor' => 890,
            'vencimento' => now()->addDays(7),
            'status' => FaturaBoleto::STATUS_REGISTERED,
            'registrado_em' => now(),
            'pdf_url' => 'https://example.com/boleto2.pdf',
        ]);

        $this->portalGetJson($user, 'portal.invoices.show', ['fatura' => $fatura->id])
            ->assertOk()
            ->assertJsonPath('data.id', $fatura->id)
            ->assertJsonPath('data.boletos.0.id', $boleto->id)
            ->assertJsonPath('data.boletos.0.pdf_download_url', 'https://'.$this->portalHost().route('boletos.pdf', ['boleto' => $boleto->id], false))
            ->assertJsonPath('data.itens.0.descricao', 'Aluguel Mensal');
    }

    public function test_denies_access_for_non_tenant_user(): void
    {
        $user = User::factory()->create();

        $this->portalGetJson($user, 'portal.contracts.index')
            ->assertStatus(403);
    }

    private function portalGetJson(User $user, string $routeName, array $params = []): TestResponse
    {
        $host = config('app.portal_domain');
        $uri = route($routeName, $params, false);

        return $this->actingAs($user)
            ->withServerVariables([
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ])
            ->getJson($uri);
    }

    private function portalHost(): string
    {
        return 'portal.fortressempreendimentos.com.br';
    }
}
