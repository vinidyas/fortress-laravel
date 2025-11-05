<?php

declare(strict_types=1);

namespace App\Domain\Cnpj;

interface CnpjLookupProvider
{
    public function key(): string;

    /**
     * @return CnpjData|null Retorna null quando o registro nao existe no provedor.
     */
    public function lookup(string $cnpj): ?CnpjData;
}
