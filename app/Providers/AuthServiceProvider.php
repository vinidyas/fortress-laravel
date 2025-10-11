<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Contrato;
use App\Models\CostCenter;
use App\Models\Fatura;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Imovel;
use App\Models\PaymentSchedule;
use App\Models\Pessoa;
use App\Policies\AuditLogPolicy;
use App\Policies\ContratoPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\FaturaPolicy;
use App\Policies\FinancialAccountPolicy;
use App\Policies\FinancialTransactionPolicy;
use App\Policies\ImovelPolicy;
use App\Policies\PaymentSchedulePolicy;
use App\Policies\PessoaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected array $policies = [
        Imovel::class => ImovelPolicy::class,
        Pessoa::class => PessoaPolicy::class,
        Contrato::class => ContratoPolicy::class,
        Fatura::class => FaturaPolicy::class,
        FinancialAccount::class => FinancialAccountPolicy::class,
        FinancialTransaction::class => FinancialTransactionPolicy::class,
        CostCenter::class => CostCenterPolicy::class,
        PaymentSchedule::class => PaymentSchedulePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
