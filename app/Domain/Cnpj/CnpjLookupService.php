<?php

declare(strict_types=1);

namespace App\Domain\Cnpj;

use App\Domain\Cnpj\Exceptions\CnpjLookupException;
use App\Domain\Cnpj\Exceptions\CnpjNotFoundException;
use App\Domain\Cnpj\Exceptions\CnpjProviderException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class CnpjLookupService
{
    /**
     * @var array<string, CnpjLookupProvider>
     */
    private array $providers = [];

    public function __construct(
        iterable $providers,
        private readonly CacheRepository $cache,
        private readonly int $cacheTtl = 86400,
    ) {
        foreach ($providers as $provider) {
            if (! $provider instanceof CnpjLookupProvider) {
                continue;
            }

            $key = $provider->key();

            $this->providers[$key] = $provider;
        }
    }

    public function lookup(string $cnpj): CnpjData
    {
        $normalized = $this->onlyDigits($cnpj);

        if (strlen($normalized) !== 14) {
            throw new CnpjLookupException('CNPJ inválido. Informe 14 dígitos.');
        }

        $cacheKey = "cnpj_lookup:{$normalized}";

        if ($this->cacheTtl > 0 && $this->cache->has($cacheKey)) {
            /** @var CnpjData $data */
            $data = $this->cache->get($cacheKey);

            return $data;
        }

        $exceptions = [];

        foreach ($this->providers as $providerKey => $provider) {
            try {
                $data = $provider->lookup($normalized);

                if ($data === null) {
                    continue;
                }

                if ($this->cacheTtl > 0) {
                    $this->cache->put($cacheKey, $data, $this->cacheTtl);
                }

                return $data;
            } catch (CnpjNotFoundException $exception) {
                $exceptions[] = $exception;
                // tenta próximo provedor
            } catch (Throwable $throwable) {
                $exceptions[] = $throwable;
                Log::warning('Falha ao consultar provedor CNPJ', [
                    'provider' => $providerKey,
                    'cnpj' => $normalized,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        $notFound = true;

        foreach ($exceptions as $exception) {
            if (! $exception instanceof CnpjNotFoundException) {
                $notFound = false;
                break;
            }
        }

        if ($notFound) {
            throw new CnpjNotFoundException($normalized);
        }

        $message = 'Nenhum provedor respondeu com sucesso para o CNPJ solicitado.';

        if ($exceptions !== []) {
            $message .= ' Últimas mensagens: ' . implode(' | ', array_map(
                static fn (Throwable $e): string => $e->getMessage(),
                $exceptions
            ));
        }

        throw new CnpjProviderException('multi-provider', null, $message);
    }

    /**
     * @return array<string>
     */
    public function providers(): array
    {
        return array_keys($this->providers);
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
