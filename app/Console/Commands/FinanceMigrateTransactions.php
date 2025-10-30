<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Finance\Support\MigrationLogger;
use App\Domain\Financeiro\Actions\DescriptionResolver;
use App\Domain\Financeiro\DataTransferObjects\InstallmentData;
use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Services\CreateJournalEntryService;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Models\FinancialTransaction;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

class FinanceMigrateTransactions extends Command
{
    protected $signature = 'finance:migrate-transactions {--chunk=200} {--dry-run}';

    protected $description = 'Migra registros de financial_transactions para journal_entries.';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CreateJournalEntryService $createJournalEntry,
        private readonly DescriptionResolver $descriptionResolver,
        private readonly MigrationLogger $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf('Iniciando migração de transações (chunk=%d, dry-run=%s)', $chunkSize, $dryRun ? 'sim' : 'não'));

        $totalMigrated = 0;

        FinancialTransaction::query()
            ->whereNull('meta->migrado_para_journal')
            ->orderBy('id')
            ->chunk($chunkSize, function (EloquentCollection $transactions) use (&$totalMigrated, $dryRun) {
                foreach ($transactions as $transaction) {
                    try {
                        $entry = $this->mapTransaction($transaction);

                        if (! $dryRun) {
                            $this->database->transaction(function () use ($transaction, $entry) {
                                $this->createJournalEntry->handle($entry);

                                $meta = $transaction->meta ?? [];
                                $meta['migrado_para_journal'] = true;
                                $transaction->meta = $meta;
                                $transaction->save();
                            });
                        }

                        $totalMigrated++;
                        $this->logger->success('transaction', $transaction->id);
                    } catch (Throwable $exception) {
                        $this->logger->failure('transaction', $transaction->id, $exception);
                        $this->error(sprintf('Falha ao migrar transação #%d: %s', $transaction->id, $exception->getMessage()));
                    }
                }
            });

        $this->info(sprintf('Migração concluída. %d registros %s.', $totalMigrated, $dryRun ? 'simulados' : 'migrados'));

        return self::SUCCESS;
    }

    private function mapTransaction(FinancialTransaction $transaction): JournalEntryData
    {
        $type = $transaction->tipo === 'credito' ? JournalEntryType::Receita : JournalEntryType::Despesa;
        $status = match ($transaction->status) {
            'pendente' => JournalEntryStatus::Pendente,
            'conciliado' => JournalEntryStatus::Pago,
            'em_atraso', 'atrasado' => JournalEntryStatus::Atrasado,
            'cancelado' => JournalEntryStatus::Cancelado,
            default => JournalEntryStatus::Planejado,
        };

        $meta = $transaction->meta ?? [];
        $dueDate = $meta['vencimento'] ?? $transaction->data_ocorrencia?->toDateString();
        $paymentDate = $meta['data_pagamento'] ?? null;

        $description = $this->descriptionResolver->resolve($transaction->descricao);

        $installments = Collection::make([
            new InstallmentData(
                numeroParcela: 1,
                movementDate: $transaction->data_ocorrencia->toDateString(),
                dueDate: $dueDate,
                paymentDate: $paymentDate,
                valorPrincipal: (float) $transaction->valor,
                valorJuros: (float) ($meta['juros'] ?? 0),
                valorMulta: (float) ($meta['multa'] ?? 0),
                valorDesconto: (float) ($meta['desconto'] ?? 0),
                valorTotal: (float) $transaction->valor,
                status: $status,
                meta: Arr::only($meta, ['observacao']),
            ),
        ]);

        return new JournalEntryData(
            type: $type,
            bankAccountId: $transaction->account_id,
            counterBankAccountId: null,
            costCenterId: $transaction->cost_center_id,
            propertyId: $meta['property_id'] ?? null,
            personId: $meta['person_id'] ?? null,
            descriptionId: $description?->id,
            descriptionCustom: $description ? null : $transaction->descricao,
            notes: $meta['observacao'] ?? null,
            referenceCode: $meta['codigo_referencia'] ?? null,
            origin: 'legacy',
            cloneOfId: null,
            movementDate: $transaction->data_ocorrencia->toDateString(),
            dueDate: $dueDate,
            paymentDate: $paymentDate,
            currency: 'BRL',
            status: $status,
            amount: (float) $transaction->valor,
            installments: $installments,
            allocations: Collection::make(),
            createdBy: $transaction->created_by,
            updatedBy: $transaction->updated_by,
        );
    }
}
