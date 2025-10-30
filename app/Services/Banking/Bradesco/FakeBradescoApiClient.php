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

        $response = [
            'id' => $id,
            'nossoNumero' => Arr::get($payload, 'numeroDocumento', $id . '001'),
            'numeroDocumento' => Arr::get($payload, 'numeroDocumento', $id),
            'linhaDigitavel' => '23790'.str_pad((string) random_int(1, 9999999999), 10, '0', STR_PAD_LEFT).'00000',
            'codigoBarras' => '2379'.str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT),
            'valor' => Arr::get($payload, 'valor', 0),
            'vencimento' => Arr::get($payload, 'vencimento', $now->copy()->addDays(5)->toDateString()),
            'status' => 'registered',
            'urlPdf' => 'https://example.test/boletos/'.$id.'.pdf',
            'criadoEm' => $now->toIso8601ZuluString(),
        ];

        $this->storage[$id] = $response;

        return $response;
    }

    public function getBoleto(string $externalId): array
    {
        return $this->storage[$externalId] ?? [
            'id' => $externalId,
            'status' => 'registered',
        ];
    }

    public function cancelBoleto(string $externalId, array $payload = []): array
    {
        $boleto = $this->getBoleto($externalId);

        $boleto['status'] = 'canceled';
        $boleto['canceladoEm'] = Carbon::now()->toIso8601ZuluString();
        $boleto['motivoCancelamento'] = Arr::get($payload, 'motivo', 'Fake cancelation');

        $this->storage[$externalId] = $boleto;

        return $boleto;
    }

    public function refreshAccessToken(bool $force = false): BankApiConfig
    {
        return parent::refreshAccessToken($force);
    }
}
