<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Finance\Support\MigrationLogger;
use App\Domain\Financeiro\DataTransferObjects\InstallmentData;
use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Services\CreateJournalEntryService;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Models\PaymentSchedule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

class FinanceMigratePaymentSchedules extends Command
{
    protected $signature = 'finance:migrate-payment-schedules {--chunk=200} {--dry-run}';

    protected $description = 'Converte agendamentos de pagamento em entradas parceladas no novo módulo.';

    public function __construct(
        private readonly CreateJournalEntryService $createJournalEntry,
        private readonly MigrationLogger $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf('Migrando agendamentos (chunk=%d, dry-run=%s)', $chunkSize, $dryRun ? 'sim' : 'não'));

        $total = 0;

        PaymentSchedule::query()
            ->whereNull('meta->migrado_para_journal')
            ->orderBy('id')
            ->chunk($chunkSize, function (EloquentCollection $schedules) use (&$total, $dryRun) {
                foreach ($schedules as $schedule) {
                    try {
                        $entry = $this->mapSchedule($schedule);

                        if (! $dryRun) {
                            $this->createJournalEntry->handle($entry);

                            $meta = $schedule->meta ?? [];
                            $meta['migrado_para_journal'] = true;
                            $schedule->meta = $meta;
                            $schedule->save();
                        }

                        $total++;
                        $this->logger->success('payment_schedule', $schedule->id);
                    } catch (Throwable $exception) {
                        $this->logger->failure('payment_schedule', $schedule->id, $exception);
                        $this->error(sprintf('Falha ao migrar agendamento #%d: %s', $schedule->id, $exception->getMessage()));
                    }
                }
            });

        $this->info(sprintf('Migração de agendamentos finalizada. %d registros %s.', $total, $dryRun ? 'simulados' : 'migrados'));

        return self::SUCCESS;
    }

    private function mapSchedule(PaymentSchedule $schedule): JournalEntryData
    {
        $meta = $schedule->meta ?? [];
        $accountId = $meta['account_id'] ?? null;

        if (! $accountId) {
            throw new \RuntimeException('Agendamento sem conta vinculada.');
        }

        $tipo = $meta['tipo'] ?? 'despesa';
        $type = $tipo === 'receita' ? JournalEntryType::Receita : JournalEntryType::Despesa;

        $valorParcela = $schedule->total_parcelas > 0
            ? round($schedule->valor_total / $schedule->total_parcelas, 2)
            : $schedule->valor_total;

        $installments = Collection::make();
        $date = $schedule->vencimento->copy();

        for ($i = 1; $i <= max(1, $schedule->total_parcelas); $i++) {
            $status = match ($schedule->status) {
                'quitado' => JournalEntryStatus::Pago,
                'em_atraso', 'atrasado' => JournalEntryStatus::Atrasado,
                'cancelado' => JournalEntryStatus::Cancelado,
                default => JournalEntryStatus::Planejado,
            };

            $installments->push(new InstallmentData(
                numeroParcela: $i,
                movementDate: $date->toDateString(),
                dueDate: $date->toDateString(),
                paymentDate: $status === JournalEntryStatus::Pago ? $date->toDateString() : null,
                valorPrincipal: (float) $valorParcela,
                valorJuros: 0,
                valorMulta: 0,
                valorDesconto: 0,
                valorTotal: (float) $valorParcela,
                status: $status,
            ));

            $date = $date->copy()->addMonth();
        }

        return new JournalEntryData(
            type: $type,
            bankAccountId: $accountId,
            counterBankAccountId: null,
            costCenterId: $meta['cost_center_id'] ?? null,
            propertyId: $meta['property_id'] ?? null,
            personId: $meta['person_id'] ?? null,
            descriptionId: null,
            descriptionCustom: $schedule->titulo,
            notes: $meta['observacao'] ?? null,
            referenceCode: $meta['codigo_referencia'] ?? null,
            origin: 'parcelado',
            cloneOfId: null,
            movementDate: $schedule->vencimento->toDateString(),
            dueDate: $schedule->vencimento->toDateString(),
            paymentDate: null,
            currency: 'BRL',
            status: JournalEntryStatus::Planejado,
            amount: (float) $schedule->valor_total,
            installments: $installments,
            allocations: Collection::make(),
        );
    }
}
