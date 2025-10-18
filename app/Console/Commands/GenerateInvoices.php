<?php

namespace App\Console\Commands;

use App\Services\FaturaGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate {--competencia=} {--contrato=}';

    protected $description = 'Gera faturas para contratos ativos na competencia informada';

    public function __construct(private readonly FaturaGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $competencia = $this->resolveCompetencia($this->option('competencia'));
        $contratoIdOption = $this->option('contrato');
        $contratoId = $contratoIdOption !== null ? (int) $contratoIdOption : null;

        $result = $this->generator->generateForCompetencia($competencia, $contratoId);

        if ($result['processed_contracts'] === 0) {
            $this->info('Nenhum contrato elegivel encontrado.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Faturas geradas: %d | Ignoradas (ja existentes): %d',
            $result['created'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }

    private function resolveCompetencia(?string $value): Carbon
    {
        if (! $value) {
            return Carbon::now()->startOfMonth();
        }

        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }

        return Carbon::parse($value)->startOfMonth();
    }
}
