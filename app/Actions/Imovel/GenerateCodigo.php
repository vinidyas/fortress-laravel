<?php

namespace App\Actions\Imovel;

use App\Models\Imovel;
use RuntimeException;

class GenerateCodigo
{
    public function __invoke(): string
    {
        return $this->generate();
    }

    public function generate(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $codigo = str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

            if (! Imovel::query()->where('codigo', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new RuntimeException('Nao foi possivel gerar um codigo unico para o imovel.');
    }
}
