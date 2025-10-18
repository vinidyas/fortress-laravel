<?php

declare(strict_types=1);

namespace App\Actions\Contrato;

use App\Models\Contrato;
use RuntimeException;

class GenerateCodigo
{
    public function __invoke(): string
    {
        return $this->generate();
    }

    public function generate(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $sequencial = str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $codigo = sprintf('CTR-%s', $sequencial);

            if (! Contrato::query()->where('codigo_contrato', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new RuntimeException('Nao foi possivel gerar um codigo unico para o contrato.');
    }
}
