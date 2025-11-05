<?php

declare(strict_types=1);

namespace App\Domain\Cnpj\Exceptions;

class CnpjNotFoundException extends CnpjLookupException
{
    public function __construct(string $cnpj)
    {
        parent::__construct("CNPJ {$cnpj} não foi encontrado em nenhum provedor configurado.");
    }
}
