<?php

declare(strict_types=1);

use App\Models\BankApiConfig;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config(['logging.channels.bradesco.level' => 'debug']);

class StubBradescoApiClient extends BradescoApiClient
{
    public function __construct()
    {
        $this->config = new BankApiConfig([
            'bank_code' => self::BANK_CODE,
            'environment' => 'sandbox',
            'client_id' => 'fake',
            'client_secret' => 'fake',
            'certificate_path' => base_path('storage/app/certs/fake-cert.pem'),
            'certificate_password' => null,
            'settings' => [
                'base_url' => 'https://example.test',
                'key_path' => base_path('storage/app/certs/fake-key.pem'),
                'key_password' => null,
            ],
        ]);
    }

    public function issueBoleto(array $payload): array
    {
        file_put_contents(
            storage_path('logs/bradesco-payload-raw.json'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return [
            'id' => 'FAKE123456789',
            'nuTitulo' => $payload['nuTitulo'] ?? '00000000000',
            'nuTituloGerado' => $payload['nuTitulo'] ?? '00000000000',
            'nossoNumero' => $payload['nuTitulo'] ?? '00000000000',
            'numeroDocumento' => $payload['nuCliente'] ?? '0000000000',
            'linhaDigitavel' => '23790000000000000000000000000000000000000000',
            'codigoBarras' => '23790000000000000000000000000000000000000000',
            'valor' => $payload['vlNominalTitulo'] ?? '0',
            'status' => 'registered',
            'vencimento' => now()->addDays(5)->toDateString(),
            'urlPdf' => 'https://example.test/boletos/FAKE123456789.pdf',
        ];
    }

    public function getBoleto(string|array $payload): array
    {
        return [];
    }

    public function cancelBoleto(string|array $payload): array
    {
        return [];
    }

    public function refreshAccessToken(bool $force = false): BankApiConfig
    {
        return $this->config;
    }
}

app()->instance(BradescoApiClient::class, new StubBradescoApiClient());

$fatura = Fatura::factory()->create([
    'valor_total' => 1500.00,
    'vencimento' => now()->addDays(3)->toDateString(),
    'status' => 'Aberta',
]);

FaturaLancamento::factory()->count(2)->create([
    'fatura_id' => $fatura->id,
    'valor_unitario' => 500.00,
    'valor_total' => 500.00,
    'categoria' => 'ALUGUEL',
]);

$fatura->refresh()->recalcTotals()->save();

$gateway = app(BradescoBoletoGateway::class);
$gateway->issue($fatura);

echo 'Payload capturado em: '.storage_path('logs/bradesco-payload-raw.json').PHP_EOL;
echo 'Log sanitizado em: '.storage_path('logs/bradesco-response.log').PHP_EOL;
