<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\Bradesco\TestBradescoAuth;
use App\Console\Commands\Bradesco\CreateDummyInvoice;
use App\Console\Commands\Bradesco\SanitizeBoletoPayloads;
use App\Console\Commands\CheckLocatarioBoletoData;
use App\Console\Commands\FinanceMigrateAccountBalances;
use App\Console\Commands\FinanceMigratePaymentSchedules;
use App\Console\Commands\FinanceMigrateTransactions;
use App\Console\Commands\ImportLegacyData;
use App\Console\Commands\ImportMccLedger;
use App\Console\Commands\SeedLegacyFinancialAccounts;
use App\Jobs\Bradesco\SyncPendingBradescoBoletos;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        ImportLegacyData::class,
        FinanceMigrateTransactions::class,
        FinanceMigratePaymentSchedules::class,
        FinanceMigrateAccountBalances::class,
        ImportMccLedger::class,
        CheckLocatarioBoletoData::class,
        TestBradescoAuth::class,
        CreateDummyInvoice::class,
        SanitizeBoletoPayloads::class,
        SeedLegacyFinancialAccounts::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new SyncPendingBradescoBoletos())
            ->everySixHours()
            ->name('sync-pending-bradesco-boletos')
            ->withoutOverlapping()
            ->onQueue('boletos');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
