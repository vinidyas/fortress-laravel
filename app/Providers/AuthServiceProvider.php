<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\BankStatement;
use App\Models\Contrato;
use App\Models\CostCenter;
use App\Models\Fatura;
use App\Models\FinancialAccount;
use App\Models\FinancialReconciliation;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\Imovel;
use App\Models\PaymentSchedule;
use App\Models\Pessoa;
use App\Models\DashboardAlert;
use App\Policies\AuditLogPolicy;
use App\Policies\BankStatementPolicy;
use App\Policies\ContratoPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\FaturaPolicy;
use App\Policies\FinancialAccountPolicy;
use App\Policies\FinancialReconciliationPolicy;
use App\Policies\FinancialTransactionPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\ImovelPolicy;
use App\Policies\PaymentSchedulePolicy;
use App\Policies\PessoaPolicy;
use App\Policies\DashboardAlertPolicy;
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
        FinancialReconciliation::class => FinancialReconciliationPolicy::class,
        JournalEntry::class => JournalEntryPolicy::class,
        CostCenter::class => CostCenterPolicy::class,
        PaymentSchedule::class => PaymentSchedulePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        DashboardAlert::class => DashboardAlertPolicy::class,
        BankStatement::class => BankStatementPolicy::class,
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
