<?php

declare(strict_types=1);

namespace App\Services\Banking\Bradesco;

use App\Models\BankApiConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class FakeBradescoApiClient extends BradescoApiClient
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $storage = [];

    public function issueBoleto(array $payload): array
    {
        $id = (string) (count($this->storage) + 1);
        $now = now();
        $nuTitulo = (string) Arr::get($payload, 'nuTitulo', $id);
        $numeroDocumento = (string) (Arr::get($payload, 'nuCliente') ?? Arr::get($payload, 'numeroDocumento', $id));
        $valor = $this->normalizeMoney(Arr::get($payload, 'vlNominalTitulo', Arr::get($payload, 'valor', 0)));
        $vencimentoBase = $this->parseVencimento(Arr::get($payload, 'dtVencimentoTitulo'), $now->copy()->addDays(5));

        $response = [
            'id' => $id,
            'nuTitulo' => $nuTitulo,
            'nuTituloGerado' => $nuTitulo,
            'nossoNumero' => $nuTitulo,
            'numeroDocumento' => $numeroDocumento,
            'linhaDigitavel' => '23790'.str_pad((string) random_int(1, 9999999999), 10, '0', STR_PAD_LEFT).'00000',
            'codigoBarras' => '2379'.str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT),
            'vlNominalTitulo' => number_format($valor, 2, '.', ''),
            'valor' => $valor,
            'vencimento' => $vencimentoBase->toDateString(),
            'dtVencimentoTitulo' => $vencimentoBase->format('d.m.Y'),
            'status' => 'registered',
            'urlPdf' => 'https://example.test/boletos/'.$id.'.pdf',
            'criadoEm' => $now->toIso8601ZuluString(),
        ];

        $this->storage[$id] = $response;
        if ($nuTitulo !== '') {
            $this->storage[$nuTitulo] = $response;
        }

        return $response;
    }

    public function getBoleto(string|array $payload): array
    {
        $externalId = $this->extractIdentifier($payload);

        return $this->storage[$externalId] ?? [
            'id' => $externalId,
            'status' => 'registered',
        ];
    }

    public function cancelBoleto(string|array $payload): array
    {
        $externalId = $this->extractIdentifier($payload);
        $context = is_array($payload) ? $payload : [];
        $boleto = $this->getBoleto($externalId);

        $boleto['status'] = 'canceled';
        $boleto['canceladoEm'] = Carbon::now()->toIso8601ZuluString();
        $boleto['motivoCancelamento'] = Arr::get($context, 'motivo', 'Fake cancelation');

        $this->storage[$boleto['id']] = $boleto;

        return $boleto;
    }

    public function refreshAccessToken(bool $force = false): BankApiConfig
    {
        return parent::refreshAccessToken($force);
    }

    protected function normalizeMoney(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        if (is_string($valor)) {
            $normalized = str_replace(['.', ','], ['', '.'], $valor);

            return (float) $normalized;
        }

        return 0.0;
    }

    protected function parseVencimento(?string $date, Carbon $fallback): Carbon
    {
        if (! $date) {
            return $fallback;
        }

        foreach (['d.m.Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $date);
            } catch (\Throwable) {
                // segue tentando formatos
            }
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    protected function extractIdentifier(string|array $payload): string
    {
        if (! is_array($payload)) {
            return (string) $payload;
        }

        return (string) ($payload['id']
            ?? $payload['nuTitulo']
            ?? $payload['nuTituloGerado']
            ?? $payload['nuTituloOriginal']
            ?? $payload['nossoNumero']
            ?? Arr::get($payload, 'tituloId', '')
        );
    }
}
