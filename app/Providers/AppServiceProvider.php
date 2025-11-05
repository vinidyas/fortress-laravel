<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Financeiro\Services\AccountBalanceService;
use App\Domain\Financeiro\Services\Reconciliation\Parsers\BankStatementParserFactory;
use App\Domain\Cnpj\CnpjLookupService;
use App\Domain\Cnpj\Providers\BrasilApiCnpjProvider;
use App\Events\Boleto\BoletoCanceled;
use App\Events\Boleto\BoletoPaid;
use App\Events\Boleto\BoletoRegistered;
use App\Events\Financeiro\AccountBalancesShouldRefresh;
use App\Models\Contrato;
use App\Models\CostCenter;
use App\Models\Fatura;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\Imovel;
use App\Models\PaymentSchedule;
use App\Models\Pessoa;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Listeners\Boleto\LogBoletoEvent;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\FakeBradescoApiClient;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Boleto\BoletoGateway;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BankStatementParserFactory::class, fn () => new BankStatementParserFactory());
        $this->app->bind(BoletoGateway::class, BradescoBoletoGateway::class);
        $this->app->singleton(BradescoApiClient::class, function ($app) {
            if (config('services.bradesco_boleto.fake')) {
                return new FakeBradescoApiClient();
            }

            return new BradescoApiClient();
        });

        $this->app->singleton(BrasilApiCnpjProvider::class, function ($app) {
            $config = (array) $app['config']->get('services.cnpj_lookup.brasilapi', []);

            return new BrasilApiCnpjProvider($config);
        });

        $this->app->singleton(CnpjLookupService::class, function ($app) {
            $config = (array) $app['config']->get('services.cnpj_lookup', []);
            $declaredProviders = array_filter(array_map('trim', $config['providers'] ?? []));

            $providerMap = [
                'brasilapi' => BrasilApiCnpjProvider::class,
            ];

            $providers = [];

            foreach ($declaredProviders as $providerKey) {
                $normalizedKey = strtolower($providerKey);

                if (! isset($providerMap[$normalizedKey])) {
                    continue;
                }

                $providers[] = $app->make($providerMap[$normalizedKey]);
            }

            if ($providers === []) {
                $providers[] = $app->make(BrasilApiCnpjProvider::class);
            }

            /** @var CacheRepository $cache */
            $cache = $app->make(CacheRepository::class);

            return new CnpjLookupService(
                providers: $providers,
                cache: $cache,
                cacheTtl: (int) ($config['cache_ttl'] ?? 60 * 60 * 24),
            );
        });

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
        Event::listen(AccountBalancesShouldRefresh::class, function () {
            app(AccountBalanceService::class)->invalidateCache();
        });

        Event::listen(
            [
                BoletoRegistered::class,
                BoletoPaid::class,
                BoletoCanceled::class,
            ],
            LogBoletoEvent::class
        );

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(optional($request->user())->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        $observer = AuditableObserver::class;

        User::observe($observer);
        Pessoa::observe($observer);
        Imovel::observe($observer);
        Contrato::observe($observer);
        Fatura::observe($observer);
        FinancialAccount::observe($observer);
        FinancialTransaction::observe($observer);
        JournalEntry::observe($observer);
        PaymentSchedule::observe($observer);
        CostCenter::observe($observer);
    }
}
