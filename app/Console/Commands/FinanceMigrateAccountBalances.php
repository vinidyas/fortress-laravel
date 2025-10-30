<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Illuminate\Console\Command;

class FinanceMigrateAccountBalances extends Command
{
    protected $signature = 'finance:migrate-account-balances {--dry-run}';

    protected $description = 'Recalcula o saldo atual das contas financeiras após a migração.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Recalculando saldos das contas financeiras'.($dryRun ? ' (simulação)' : ''));

        $query = FinancialAccount::query()->orderBy('id');

        $query->chunk(100, function ($accounts) use ($dryRun) {
            foreach ($accounts as $account) {
                $saldo = (float) $account->saldo_inicial;

                $totals = JournalEntry::query()
                    ->selectRaw('type, SUM(amount) as total')
                    ->where('bank_account_id', $account->id)
                    ->where('status', 'pago')
                    ->groupBy('type')
                    ->pluck('total', 'type');

                $saldo += (float) ($totals['receita'] ?? 0);
                $saldo -= (float) ($totals['despesa'] ?? 0);
                $saldo -= (float) ($totals['transferencia'] ?? 0);

                $incomingTransfers = JournalEntry::query()
                    ->where('counter_bank_account_id', $account->id)
                    ->where('type', 'transferencia')
                    ->where('status', 'pago')
                    ->sum('amount');

                $saldo += (float) $incomingTransfers;

                $this->line(sprintf('Conta #%d: saldo recalculado para %0.2f', $account->id, $saldo));

                if (! $dryRun) {
                    $account->saldo_atual = $saldo;
                    $account->save();
                }
            }
        });

        $this->info('Processo concluído.');

        return self::SUCCESS;
    }
}
