<?php

namespace App\Console\Commands;

use App\Models\FinancialAccount;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedLegacyFinancialAccounts extends Command
{
    protected $signature = 'finance:seed-legacy-accounts {--force-update : Atualiza saldos mesmo se a conta já existir e tiver lançamentos}';

    protected $description = 'Cria contas bancárias herdadas do MCC com saldos iniciais';

    /**
     * @var array<string, array{balance: float, active: bool}>
     */
    private array $accounts = [
        'FORTRESS BRA' => ['balance' => 2118158.80, 'active' => true],
        'FORTRESS SIC' => ['balance' => 1154.19, 'active' => false],
        'PSS ITAU' => ['balance' => 2251.25, 'active' => false],
        'PSS BRADESCO' => ['balance' => 867.33, 'active' => true],
        'OUTROS' => ['balance' => 1525352.46, 'active' => true],
        'SC BRADESCO' => ['balance' => 1338595.26, 'active' => false],
        'MIX SICOOB' => ['balance' => 0.0, 'active' => false],
        'CYRO BRADESC' => ['balance' => 1232.97, 'active' => true],
        'CYRO ORIGINA' => ['balance' => -192.92, 'active' => true],
        'BANK OF CYRO' => ['balance' => 0.0, 'active' => false],
        'BMC SICOOB' => ['balance' => 0.0, 'active' => false],
        'MEX SANTANDE' => ['balance' => 50.00, 'active' => false],
        'RURAL BRASDE' => ['balance' => 0.0, 'active' => false],
        'GOYTACAZ' => ['balance' => 26176.88, 'active' => true],
        'PEDRA VERDE' => ['balance' => 81.36, 'active' => true],
        'FORTRESS INV' => ['balance' => 6500000.00, 'active' => true],
        'FORTRESS GVL' => ['balance' => 0.0, 'active' => false],
        'FORTRESS RSL' => ['balance' => 0.0, 'active' => true],
        'FORTRESS LVL' => ['balance' => 0.0, 'active' => true],
        'FORTRESS CFL' => ['balance' => 0.0, 'active' => false],
        'INOVA FOODS' => ['balance' => 0.0, 'active' => true],
        'C.C BRADESCO' => ['balance' => 0.0, 'active' => true],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force-update');
        $date = Carbon::now()->toDateString();
        $created = 0;
        $updated = 0;

        foreach ($this->accounts as $name => $config) {
            $account = FinancialAccount::query()->whereRaw('LOWER(nome) = ?', [Str::lower($name)])->first();

            if (! $account) {
                $account = new FinancialAccount([
                    'nome' => $name,
                    'apelido' => $name,
                    'tipo' => 'conta_corrente',
                    'moeda' => 'BRL',
                    'categoria' => 'operacional',
                    'permite_transf' => true,
                    'padrao_recebimento' => false,
                    'padrao_pagamento' => false,
                ]);
                $created += 1;
            }

            $account->ativo = $config['active'];

            $shouldUpdateBalances = $force;

            if (! $shouldUpdateBalances) {
                $hasMovements = $account->exists && $account->journalEntries()->exists();
                $shouldUpdateBalances = ! $hasMovements;
            }

            if ($shouldUpdateBalances) {
                $account->saldo_inicial = $config['balance'];
                $account->saldo_atual = $config['balance'];
                $account->data_saldo_inicial = $date;
            }

            if (! $account->exists) {
                $account->saldo_inicial ??= $config['balance'];
                $account->saldo_atual ??= $config['balance'];
                $account->data_saldo_inicial ??= $date;
            }

            $account->save();

            if ($account->wasChanged()) {
                $updated += 1;
            }
        }

        $this->info(sprintf('Contas criadas: %d | atualizadas: %d', $created, $updated));
        $this->comment('Para forçar atualização dos saldos use --force-update');

        return Command::SUCCESS;
    }
}
