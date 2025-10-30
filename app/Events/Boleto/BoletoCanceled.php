<?php

declare(strict_types=1);

namespace App\Events\Boleto;

use App\Models\Fatura;
use App\Models\FaturaBoleto;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BoletoCanceled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Fatura $fatura,
        public readonly FaturaBoleto $boleto
    ) {
    }
}
