<?php

namespace App\Services\Banking\Bradesco;

use App\Models\BankApiConfig;
use App\Services\Banking\Bradesco\Support\BradescoPayloadSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
        $endpoint = '/boleto/cobranca-registro/v1/cobranca';
        $response = $this->request()->post($endpoint, $payload);

        if ($response->failed()) {
            $this->logError('Emissão de boleto', $endpoint, $payload, $response);
            $response->throw();
        }

        $this->logSuccess('Emissão de boleto', $endpoint, $payload, $response);

        return $response->json();
    }

    public function getBoleto(string|array $payload): array
    {
        $body = $this->buildConsultaPayload($payload);

        $endpoint = '/boleto/cobranca-consulta/v1/consultar';
        $response = $this->request()->post($endpoint, $body);

        if ($response->failed()) {
            $this->logError('Consulta de boleto', $endpoint, $body, $response);
            $response->throw();
        }

        $this->logSuccess('Consulta de boleto', $endpoint, $body, $response);

        return $response->json();
    }

    public function downloadBoletoPdf(string|array $payload): Response
    {
        $body = $this->buildConsultaPayload($payload);
        $endpoints = [
            '/boleto/cobranca-pdf/v1',
            '/boleto/cobranca-pdf/v1/cobranca',
            '/boleto/cobranca-pdf/v1/obter',
            '/boleto/cobranca-consulta/v1/consultar',
        ];

        $lastResponse = null;
        $lastEndpoint = null;

        foreach ($endpoints as $endpoint) {
            $response = $this->request('application/pdf')->post($endpoint, $body);
            $contentType = Str::lower($response->header('content-type', ''));

            if ($response->successful() && Str::contains($contentType, 'application/pdf')) {
                $this->logSuccess('Download de PDF', $endpoint, $body, $response);

                return $response;
            }

            $lastResponse = $response;
            $lastEndpoint = $endpoint;

            if (! in_array($response->status(), [404, 405], true)) {
                break;
            }
        }

        if ($lastResponse) {
            $this->logError('Download de PDF', $lastEndpoint ?? $endpoints[0], $body, $lastResponse);
            $lastResponse->throw();
        }

        throw new RuntimeException('Falha ao requisitar PDF do Bradesco.');
    }

    public function cancelBoleto(string|array $payload): array
    {
        $body = is_array($payload) ? $payload : [
            'nuTitulo' => (string) $payload,
        ];

        $endpoint = '/boleto/cobranca-baixa/v1/baixar';
        $response = $this->request()->post($endpoint, $body);

        if ($response->failed()) {
            $this->logError('Baixa de boleto', $endpoint, $body, $response);
            $response->throw();
        }

        $this->logSuccess('Baixa de boleto', $endpoint, $body, $response);

        return $response->json();
    }

    public function refreshAccessToken(bool $force = false): BankApiConfig
    {
        if ($force || $this->config->shouldRefreshToken()) {
            $this->obtainAccessToken();
        }

        return $this->config;
    }

    protected function request(string $accept = 'application/json'): PendingRequest
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
            ->accept($accept);
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

    protected function logError(string $operation, string $endpoint, array $payload, Response $response): void
    {
        Log::channel('bradesco')->error("[Bradesco] {$operation} falhou", [
            'endpoint' => $endpoint,
            'payload' => BradescoPayloadSanitizer::sanitize($payload),
            'response' => $this->sanitizeResponse($response),
        ]);
    }

    protected function logSuccess(string $operation, string $endpoint, array $payload, Response $response): void
    {
        Log::channel('bradesco')->info("[Bradesco] {$operation} concluída", [
            'endpoint' => $endpoint,
            'payload' => BradescoPayloadSanitizer::sanitize($payload),
            'response' => $this->sanitizeResponse($response),
        ]);
    }

    protected function sanitizeResponse(Response $response): array
    {
        $headers = array_map(function ($values) {
            return implode(', ', (array) $values);
        }, $response->headers());

        $contentType = Str::lower($response->header('content-type', ''));
        $body = null;

        try {
            $decoded = $response->json();
        } catch (\Throwable) {
            $decoded = null;
        }

        if (is_array($decoded)) {
            $body = BradescoPayloadSanitizer::sanitize($decoded);
        } elseif (Str::contains($contentType, 'application/json')) {
            $body = $response->body();
        } else {
            $length = strlen((string) $response->body());
            $label = $contentType !== '' ? $contentType : 'binary';
            $body = sprintf('[%s response truncated: %d bytes]', $label, $length);
        }

        return [
            'status' => $response->status(),
            'body' => $body,
            'headers' => $headers,
        ];
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

        $nossoNumero = Arr::get($requested, 'nossoNumero');
        if ($nossoNumero !== null && $nossoNumero !== '') {
            $nossoNumero = Str::padLeft((string) $nossoNumero, 11, '0');
        }

        $consultaNegociacao = Arr::get(
            $services,
            'consulta_negociacao',
            Arr::get($services, 'negociacao')
        );

        $defaults = [
            'sequencia' => Arr::get($requested, 'sequencia', '0'),
            'produto' => Arr::get($services, 'id_produto'),
            'negociacao' => $consultaNegociacao,
            'nossoNumero' => $nossoNumero,
            'nuTitulo' => $nossoNumero,
            'status' => Arr::get($requested, 'status', '0'),
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
