<?php

namespace App\Services\Banking\Bradesco;

use App\Models\BankApiConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class BradescoApiClient
{
    public const BANK_CODE = 'bradesco';

    protected BankApiConfig $config;

    public function __construct(?BankApiConfig $config = null)
    {
        $this->config = $config ?? $this->resolveConfig();
    }

    public function issueBoleto(array $payload): array
    {
        return $this->request()
            ->post('/boleto/cobranca-registro/v1/cobranca', $payload)
            ->throw()
            ->json();
    }

    public function getBoleto(string|array $payload): array
    {
        $body = $this->buildConsultaPayload($payload);

        return $this->request()
            ->post('/boleto/cobranca-consulta/v1/consultar', $body)
            ->throw()
            ->json();
    }

    public function cancelBoleto(string|array $payload): array
    {
        $body = is_array($payload) ? $payload : [
            'nuTitulo' => (string) $payload,
        ];

        return $this->request()
            ->post('/boleto/cobranca-baixa/v1/baixar', $body)
            ->throw()
            ->json();
    }

    public function refreshAccessToken(bool $force = false): BankApiConfig
    {
        if ($force || $this->config->shouldRefreshToken()) {
            $this->obtainAccessToken();
        }

        return $this->config;
    }

    protected function request(): PendingRequest
    {
        $config = config('services.bradesco_boleto');

        $baseUrl = $this->config->settings['base_url'] ?? $config['base_url'] ?? '';
        $timeout = Arr::get($config, 'timeout', 10);

        $this->refreshAccessToken();

        return $this->httpClient()
            ->baseUrl($baseUrl)
            ->timeout($timeout)
            ->asJson()
            ->withToken((string) $this->config->access_token)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);
    }

    protected function resolveConfig(): BankApiConfig
    {
        $targetEnvironment = config('services.bradesco_boleto.environment', 'sandbox');

        $query = BankApiConfig::query()
            ->where('bank_code', self::BANK_CODE)
            ->where('environment', $targetEnvironment);

        $config = (clone $query)->where('active', true)->first() ?? $query->first();

        if (! $config) {
            $config = new BankApiConfig([
                'bank_code' => self::BANK_CODE,
                'environment' => $targetEnvironment,
                'client_id' => config('services.bradesco_boleto.client_id'),
                'client_secret' => config('services.bradesco_boleto.client_secret'),
                'certificate_path' => config('services.bradesco_boleto.cert_path'),
                'certificate_password' => config('services.bradesco_boleto.cert_password'),
                'webhook_secret' => config('services.bradesco_boleto.webhook_secret'),
                'settings' => [
                    'base_url' => config('services.bradesco_boleto.base_url'),
                    'key_path' => config('services.bradesco_boleto.key_path'),
                    'key_password' => config('services.bradesco_boleto.key_password'),
                ],
            ]);
        }

        return $config;
    }

    protected function httpClient(): PendingRequest
    {
        $certPath = $this->config->certificate_path ?: config('services.bradesco_boleto.cert_path');
        $certPassword = $this->config->certificate_password ?: config('services.bradesco_boleto.cert_password');
        $keyPath = Arr::get($this->config->settings, 'key_path', config('services.bradesco_boleto.key_path'));
        $keyPassword = Arr::get($this->config->settings, 'key_password', config('services.bradesco_boleto.key_password'));

        if (! $certPath || ! $keyPath) {
            throw new RuntimeException('Certificado ou chave privada não configurados para integração Bradesco.');
        }

        $options = [
            'cert' => $certPassword ? [$certPath, $certPassword] : $certPath,
            'ssl_key' => $keyPassword ? [$keyPath, $keyPassword] : $keyPath,
        ];

        return Http::withOptions($options);
    }

    protected function obtainAccessToken(): void
    {
        $config = config('services.bradesco_boleto');
        $baseUrl = $this->config->settings['base_url'] ?? $config['base_url'] ?? '';

        $response = $this->httpClient()
            ->baseUrl($baseUrl)
            ->asForm()
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('/auth/server-mtls/v2/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config->client_id,
                'client_secret' => $this->config->client_secret,
            ])->throw();

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['access_token'])) {
            Log::channel('bradesco')->error('[Bradesco] Falha ao obter token', ['response' => $payload]);
            throw new RuntimeException('Não foi possível obter o token de acesso do Bradesco.');
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 0);

        $this->config->forceFill([
            'access_token' => $payload['access_token'],
            'token_expires_at' => now()->addSeconds(max($expiresIn - 60, 60)),
            'certificate_path' => $this->config->certificate_path ?: config('services.bradesco_boleto.cert_path'),
            'certificate_password' => $this->config->certificate_password ?: config('services.bradesco_boleto.cert_password'),
            'settings' => array_merge($this->config->settings ?? [], [
                'base_url' => $baseUrl,
                'key_path' => Arr::get($this->config->settings, 'key_path', config('services.bradesco_boleto.key_path')),
                'key_password' => Arr::get($this->config->settings, 'key_password', config('services.bradesco_boleto.key_password')),
            ]),
        ]);

        $this->config->save();

        Log::channel('bradesco')->info('[Bradesco] Token atualizado', [
            'bank_api_config_id' => $this->config->id,
            'expires_in' => $expiresIn,
        ]);
    }

    /**
     * @param  string|array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildConsultaPayload(string|array $payload): array
    {
        $services = config('services.bradesco_boleto', []);
        $requested = is_array($payload) ? $payload : [
            'nossoNumero' => (string) $payload,
        ];

        if (! isset($requested['nossoNumero']) && isset($requested['nuTitulo'])) {
            $requested['nossoNumero'] = (string) $requested['nuTitulo'];
        }

        $defaults = [
            'sequencia' => Arr::get($requested, 'sequencia', '0'),
            'produto' => Arr::get($services, 'id_produto'),
            'negociacao' => Arr::get($services, 'negociacao'),
            'nossoNumero' => Arr::get($requested, 'nossoNumero'),
            'cpfCnpj' => [
                'cpfCnpj' => Str::padLeft((string) Arr::get($services, 'cnpj_raiz', ''), 8, '0'),
                'filial' => Str::padLeft((string) Arr::get($services, 'cnpj_filial', ''), 4, '0'),
                'controle' => Str::padLeft((string) Arr::get($services, 'cnpj_controle', ''), 2, '0'),
            ],
        ];

        if (isset($requested['cpfCnpj']) && is_array($requested['cpfCnpj'])) {
            $defaults['cpfCnpj'] = array_replace($defaults['cpfCnpj'], $requested['cpfCnpj']);
        }

        return array_replace($defaults, Arr::except($requested, ['cpfCnpj']));
    }
}
