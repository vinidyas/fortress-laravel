<?php

declare(strict_types=1);

namespace App\Console\Commands\Bradesco;

use App\Models\FaturaBoleto;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\Support\BradescoPayloadSanitizer;
use Illuminate\Console\Command;

class SanitizeBoletoPayloads extends Command
{
    protected $signature = 'bradesco:sanitize-boleto-payloads {--chunk=100 : Quantidade de registros por lote} {--bank=bradesco : Código do banco (default: bradesco)} {--dry-run : Apenas calcula quantos registros seriam atualizados}';

    protected $description = 'Sanitiza payloads históricos de boletos Bradesco removendo dados sensíveis.';

    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 10);
        $bankCode = (string) $this->option('bank');
        $dryRun = (bool) $this->option('dry-run');

        $query = FaturaBoleto::query()
            ->where('bank_code', $bankCode)
            ->where(function ($inner) {
                $inner->whereNotNull('payload')
                    ->orWhereNotNull('response_payload')
                    ->orWhereNotNull('webhook_payload');
            });

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nenhum boleto encontrado para sanitização.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Processando %d boletos (chunk %d)...', $total, $chunkSize));

        $updated = 0;

        $query->orderBy('id')
            ->chunkById($chunkSize, function ($boletos) use (&$updated, $dryRun) {
                /** @var FaturaBoleto $boleto */
                foreach ($boletos as $boleto) {
                    $dirty = false;

                    foreach (['payload', 'response_payload', 'webhook_payload'] as $attribute) {
                        $value = $boleto->getAttribute($attribute);
                        if (! is_array($value) || $value === []) {
                            continue;
                        }

                        $sanitized = BradescoPayloadSanitizer::sanitize($value);
                        if ($sanitized !== $value) {
                            $dirty = true;
                            $boleto->setAttribute($attribute, $sanitized);
                        }
                    }

                    if (! $dirty) {
                        continue;
                    }

                    $updated++;

                    if (! $dryRun) {
                        $boleto->save();
                    }
                }
            });

        if ($updated === 0) {
            $this->info('Todos os boletos já estavam sanitizados.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn(sprintf('%d boletos seriam atualizados (execução com --dry-run).', $updated));

            return self::SUCCESS;
        }

        $this->info(sprintf('%d boletos sanitizados com sucesso.', $updated));

        return self::SUCCESS;
    }
}
