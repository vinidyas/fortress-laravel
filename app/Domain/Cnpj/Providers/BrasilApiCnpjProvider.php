<?php

declare(strict_types=1);

namespace App\Domain\Cnpj\Providers;

use App\Domain\Cnpj\CnpjData;
use App\Domain\Cnpj\CnpjLookupProvider;
use App\Domain\Cnpj\Exceptions\CnpjProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class BrasilApiCnpjProvider implements CnpjLookupProvider
{
    private string $baseUrl;
    private float $timeout;
    private int $retryTimes;
    private int $retrySleep;

    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim((string) Arr::get($config, 'base_url', 'https://brasilapi.com.br/api'), '/');
        $this->timeout = (float) Arr::get($config, 'timeout', 5.0);
        $this->retryTimes = (int) Arr::get($config, 'retry_times', 1);
        $this->retrySleep = (int) Arr::get($config, 'retry_sleep', 200);
    }

    public function key(): string
    {
        return 'brasilapi';
    }

    public function lookup(string $cnpj): ?CnpjData
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleep)
            ->acceptJson()
            ->get("/cnpj/v1/{$cnpj}");

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new CnpjProviderException(
                $this->key(),
                $response->status(),
                $response->json('message') ?? 'Resposta inesperada do BrasilAPI.'
            );
        }

        try {
            $payload = $response->json();

            if (! is_array($payload)) {
                throw new CnpjProviderException($this->key(), $response->status(), 'Payload inválido do BrasilAPI.');
            }

            // Alguns retornos da BrasilAPI trazem endereço como campos de raiz (cep, uf, municipio, bairro, logradouro, numero, complemento)
            // e em outros como objeto "endereco". Fazemos fallback para a raiz quando o objeto não existir.
            $rawEndereco = Arr::get($payload, 'endereco', []);
            if (!is_array($rawEndereco) || $rawEndereco === []) {
                $tipoLogradouro = trim((string) ($payload['descricao_tipo_de_logradouro'] ?? ''));
                $logradouroRaiz = trim((string) ($payload['logradouro'] ?? ''));
                $logradouro = $logradouroRaiz;
                if ($tipoLogradouro !== '' && $logradouroRaiz !== '') {
                    $logradouro = $tipoLogradouro . ' ' . $logradouroRaiz;
                } elseif ($tipoLogradouro !== '' && $logradouroRaiz === '') {
                    $logradouro = $tipoLogradouro;
                }

                $rawEndereco = [
                    'cep' => Arr::get($payload, 'cep'),
                    'uf' => Arr::get($payload, 'uf'),
                    'municipio' => Arr::get($payload, 'municipio'),
                    'bairro' => Arr::get($payload, 'bairro'),
                    'logradouro' => $logradouro !== '' ? $logradouro : null,
                    'numero' => Arr::get($payload, 'numero'),
                    'complemento' => Arr::get($payload, 'complemento'),
                ];
            }

            $telefone = $this->firstNonEmpty(
                Arr::get($payload, 'ddd_telefone_1'),
                Arr::get($payload, 'ddd_telefone_2'),
                Arr::get($payload, 'ddd_fax')
            );

            $cep = Arr::get($rawEndereco, 'cep');
            if (is_string($cep)) {
                $cep = preg_replace('/\D/', '', $cep) ?: null;
            }

            return new CnpjData(
                cnpj: $cnpj,
                razaoSocial: (string) Arr::get($payload, 'razao_social', ''),
                nomeFantasia: $this->nullableString(Arr::get($payload, 'nome_fantasia')),
                email: $this->nullableString(Arr::get($payload, 'email')),
                telefone: $this->nullableString($telefone),
                cep: $cep,
                uf: $this->nullableString(Arr::get($rawEndereco, 'uf')),
                municipio: $this->nullableString(Arr::get($rawEndereco, 'municipio')),
                bairro: $this->nullableString(Arr::get($rawEndereco, 'bairro')),
                logradouro: $this->nullableString(Arr::get($rawEndereco, 'logradouro')),
                numero: $this->nullableString(Arr::get($rawEndereco, 'numero')),
                complemento: $this->nullableString(Arr::get($rawEndereco, 'complemento')),
                provider: $this->key(),
                fetchedAt: CarbonImmutable::now(),
                raw: $payload,
            );
        } catch (Throwable $throwable) {
            if ($throwable instanceof CnpjProviderException) {
                throw $throwable;
            }

            throw new CnpjProviderException($this->key(), $response->status(), $throwable->getMessage(), $throwable);
        }
    }

    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
