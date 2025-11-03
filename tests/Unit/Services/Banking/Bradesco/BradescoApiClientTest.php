<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Banking\Bradesco;

use App\Models\BankApiConfig;
use App\Services\Banking\Bradesco\BradescoApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BradescoApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function testResolveConfigPrefersActiveRecordForCurrentEnvironment(): void
    {
        BankApiConfig::query()->create([
            'bank_code' => BradescoApiClient::BANK_CODE,
            'environment' => 'sandbox',
            'client_id' => 'sandbox-client',
            'client_secret' => 'secret',
            'active' => true,
        ]);

        $activeProduction = BankApiConfig::query()->create([
            'bank_code' => BradescoApiClient::BANK_CODE,
            'environment' => 'production',
            'client_id' => 'prod-client-active',
            'client_secret' => 'secret',
            'active' => true,
        ]);

        config()->set('services.bradesco_boleto.environment', 'production');

        $client = new class extends BradescoApiClient {
            public function config(): BankApiConfig
            {
                return $this->config;
            }
        };

        $resolved = $client->config();

        $this->assertTrue($resolved->is($activeProduction));
    }

    public function testGetBoletoBuildsPayloadWithRequiredFields(): void
    {
        config()->set('services.bradesco_boleto', array_merge(
            config('services.bradesco_boleto', []),
            [
                'environment' => 'sandbox',
                'base_url' => 'https://sandbox.example',
                'id_produto' => '09',
                'negociacao' => '386100000000041000',
                'cnpj_raiz' => '51543631',
                'cnpj_filial' => '0001',
                'cnpj_controle' => '98',
            ]
        ));

        $config = BankApiConfig::query()->create([
            'bank_code' => BradescoApiClient::BANK_CODE,
            'environment' => 'sandbox',
            'client_id' => 'client-id',
            'client_secret' => 'secret',
            'access_token' => 'token',
            'token_expires_at' => now()->addMinutes(10),
            'active' => true,
        ]);

        Http::fake([
            'https://sandbox.example/boleto/cobranca-consulta/v1/consultar' => Http::response(['status' => 'ok'], 200),
        ]);

        $client = new BradescoApiClient($config);
        $client->getBoleto('51470000241');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://sandbox.example/boleto/cobranca-consulta/v1/consultar'
                && $data['sequencia'] === '0'
                && $data['produto'] === '09'
                && $data['negociacao'] === '386100000000041000'
                && $data['nossoNumero'] === '51470000241'
                && $data['cpfCnpj']['cpfCnpj'] === '51543631'
                && $data['cpfCnpj']['filial'] === '0001'
                && $data['cpfCnpj']['controle'] === '98';
        });
    }
}
