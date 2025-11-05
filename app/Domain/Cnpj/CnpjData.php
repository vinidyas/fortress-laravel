<?php

declare(strict_types=1);

namespace App\Domain\Cnpj;

use Carbon\CarbonImmutable;

class CnpjData
{
    public function __construct(
        public readonly string $cnpj,
        public readonly string $razaoSocial,
        public readonly ?string $nomeFantasia,
        public readonly ?string $email,
        public readonly ?string $telefone,
        public readonly ?string $cep,
        public readonly ?string $uf,
        public readonly ?string $municipio,
        public readonly ?string $bairro,
        public readonly ?string $logradouro,
        public readonly ?string $numero,
        public readonly ?string $complemento,
        public readonly string $provider,
        public readonly CarbonImmutable $fetchedAt,
        public readonly array $raw = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'cnpj' => $this->cnpj,
            'razao_social' => $this->razaoSocial,
            'nome_fantasia' => $this->nomeFantasia,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'cep' => $this->cep,
            'uf' => $this->uf,
            'municipio' => $this->municipio,
            'bairro' => $this->bairro,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'provider' => $this->provider,
            'fetched_at' => $this->fetchedAt->toIso8601String(),
        ];
    }
}
