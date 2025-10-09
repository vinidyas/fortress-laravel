<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Contrato;
use App\Models\CostCenter;
use App\Models\Fatura;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Imovel;
use App\Models\PaymentSchedule;
use App\Models\Pessoa;
use App\Models\User;
use App\Observers\AuditableObserver;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'database.connections.legacy' => [
                'driver' => env('LEGACY_DB_CONNECTION', 'mysql'),
                'host' => env('LEGACY_DB_HOST', env('DB_HOST', '127.0.0.1')),
                'port' => env('LEGACY_DB_PORT', env('DB_PORT', '3306')),
                'database' => env('LEGACY_DB_DATABASE', env('DB_DATABASE', 'forge')),
                'username' => env('LEGACY_DB_USERNAME', env('DB_USERNAME', 'forge')),
                'password' => env('LEGACY_DB_PASSWORD', env('DB_PASSWORD', '')),
                'unix_socket' => env('LEGACY_DB_SOCKET', env('DB_SOCKET', '')),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ],
        ]);
    }

    public function boot(): void
    {
        $observer = AuditableObserver::class;

        User::observe($observer);
        Pessoa::observe($observer);
        Imovel::observe($observer);
        Contrato::observe($observer);
        Fatura::observe($observer);
        FinancialAccount::observe($observer);
        FinancialTransaction::observe($observer);
        PaymentSchedule::observe($observer);
        CostCenter::observe($observer);
    }
}