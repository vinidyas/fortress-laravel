<?php

declare(strict_types=1);

namespace App\Domain\Cnpj\Exceptions;

use Throwable;

class CnpjProviderException extends CnpjLookupException
{
    public function __construct(
        public readonly string $provider,
        public readonly ?int $statusCode = null,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        $reason = $message !== '' ? $message : 'Falha ao consultar provedor CNPJ.';
        $statusMessage = $statusCode !== null ? " (status {$statusCode})" : '';

        parent::__construct("[{$provider}] {$reason}{$statusMessage}", 0, $previous);
    }
}
