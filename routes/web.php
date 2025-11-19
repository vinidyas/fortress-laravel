<?php

use App\Http\Controllers\Admin\AdminDashboardPageController;
use App\Http\Controllers\Admin\AdminRolePageController;
use App\Http\Controllers\Admin\AdminUserPageController;
use App\Http\Controllers\AlertHistoryPageController;
use App\Http\Controllers\AuditTrailPageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaturaBillingController;
use App\Http\Controllers\FaturaReceiptController;
use App\Http\Controllers\Financeiro\BankReconciliationPageController;
use App\Http\Controllers\Financeiro\FinanceiroPageController;
use App\Http\Controllers\Financeiro\PaymentSchedulePageController;
use App\Http\Controllers\Financeiro\SettingsPageController;
use App\Http\Controllers\Api\Reports\ReportBankLedgerController;
use App\Http\Controllers\Reports\BankAccountStatementReportPageController;
use App\Http\Controllers\Reports\BankLedgerReportPageController;
use App\Http\Controllers\Reports\FinanceiroReportPageController;
use App\Http\Controllers\Reports\GeneralAnalyticReportPageController;
use App\Http\Controllers\Reports\ImoveisReportPageController;
use App\Http\Controllers\Reports\ContratosReportPageController;
use App\Http\Controllers\Reports\OperacionalReportPageController;
use App\Http\Controllers\Reports\PessoasReportPageController;
use App\Http\Controllers\Reports\RevenueLedgerReportPageController;
use App\Http\Controllers\Reports\BankStatementReportPageController;
use App\Http\Requests\Reports\ReportBankLedgerFilterRequest;
use App\Http\Controllers\Profile\AccountController;
use App\Http\Controllers\Profile\PasswordController;
use App\Http\Controllers\Portal\ContratoController as PortalContratoController;
use App\Http\Controllers\Portal\FaturaController as PortalFaturaController;
use App\Http\Controllers\Api\Financeiro\BoletoPdfController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/perfil', [AccountController::class, 'edit'])->name('profile.edit');
    Route::match(['POST', 'PUT'], '/perfil', [AccountController::class, 'update'])->name('profile.update');
    Route::get('/perfil/senha', [PasswordController::class, 'edit'])->name('profile.password.edit');
    Route::put('/perfil/senha', [PasswordController::class, 'update'])->name('profile.password.update');

    Route::get('/imoveis', fn () => Inertia::render('Imoveis/Index'))->name('imoveis.index');
    Route::get('/imoveis/novo', fn () => Inertia::render('Imoveis/Edit'))->name('imoveis.create');
    Route::get('/imoveis/{imovel}', fn (int $imovel) => Inertia::render('Imoveis/Edit', [
        'imovelId' => $imovel,
    ]))->name('imoveis.edit');

    Route::get('/imoveis/{imovel}/visualizar', fn (int $imovel) => Inertia::render('Imoveis/Show', [
        'imovelId' => $imovel,
    ]))->name('imoveis.show');

    Route::get('/pessoas', fn () => Inertia::render('Pessoas/Index'))->name('pessoas.index');
    Route::get('/pessoas/novo', fn () => Inertia::render('Pessoas/Edit'))->name('pessoas.create');
    Route::get('/pessoas/{pessoa}', fn (int $pessoa) => Inertia::render('Pessoas/Edit', [
        'pessoaId' => $pessoa,
    ]))->name('pessoas.edit');

    // CondomÃ­nios
    Route::get('/condominios', fn () => Inertia::render('Condominios/Index'))->name('condominios.index');
    Route::get('/condominios/novo', fn () => Inertia::render('Condominios/Edit'))->name('condominios.create');
    Route::get('/condominios/{condominio}', fn (int $condominio) => Inertia::render('Condominios/Edit', [
        'condominioId' => $condominio,
    ]))->name('condominios.edit');
    Route::get('/condominios/{condominio}/visualizar', fn (int $condominio) => Inertia::render('Condominios/Show', [
        'condominioId' => $condominio,
    ]))->name('condominios.show');

    Route::get('/cadastros', fn () => Inertia::render('Cadastros/Index'))->name('cadastros.index');

    Route::get('/contratos', fn () => Inertia::render('Contratos/Index'))->name('contratos.index');
    Route::get('/contratos/novo', fn () => Inertia::render('Contratos/Edit'))->name('contratos.create');
    Route::get('/contratos/{contrato}/visualizar', fn (int $contrato) => Inertia::render('Contratos/Show', [
        'contratoId' => $contrato,
    ]))->name('contratos.show');
    Route::get('/contratos/{contrato}', fn (int $contrato) => Inertia::render('Contratos/Edit', [
        'contratoId' => $contrato,
    ]))->name('contratos.edit');

    Route::get('/faturas', fn () => Inertia::render('Faturas/Index'))->name('faturas.index');
    Route::get('/faturas/novo', fn () => Inertia::render('Faturas/Show', [
        'faturaId' => null,
    ]))->name('faturas.create');
    Route::get('/faturas/{fatura}/cobranca', FaturaBillingController::class)->name('faturas.billing');
    Route::get('/faturas/{fatura}/recibo', FaturaReceiptController::class)->name('faturas.receipt');
    Route::get('/faturas/{fatura}', fn (int $fatura) => Inertia::render('Faturas/Show', [
        'faturaId' => $fatura,
    ]))->name('faturas.show');

    Route::get('/financeiro', [FinanceiroPageController::class, 'index'])->name('financeiro.index');
    Route::get('/financeiro/lancamentos/novo', [FinanceiroPageController::class, 'create'])->name('financeiro.entries.create');
    Route::get('/financeiro/lancamentos/{journalEntry}', [FinanceiroPageController::class, 'edit'])->name('financeiro.entries.edit');
    Route::get('/financeiro/contas', [SettingsPageController::class, 'accounts'])->name('financeiro.accounts');
    Route::get('/financeiro/centros', [SettingsPageController::class, 'costCenters'])->name('financeiro.cost-centers');
    Route::get('/financeiro/conciliacao', [BankReconciliationPageController::class, 'index'])->name('financeiro.reconciliation');
    Route::get('/financeiro/agendamentos', [PaymentSchedulePageController::class, 'index'])->name('financeiro.payment-schedules');
    Route::get('/financeiro/agendamentos/novo', [PaymentSchedulePageController::class, 'create'])->name('financeiro.payment-schedules.create');
    Route::get('/boletos/{boleto}/pdf', [BoletoPdfController::class, 'show'])->name('boletos.pdf');

    Route::get('/auditoria', [AuditTrailPageController::class, 'index'])->name('auditoria.index');
    Route::get('/alertas/historico', AlertHistoryPageController::class)->name('alerts.history');

    Route::prefix('relatorios')->group(function () {
        Route::get('financeiro', [FinanceiroReportPageController::class, 'index'])->name('relatorios.financeiro');
        Route::get('extratos', [BankStatementReportPageController::class, 'index'])->name('relatorios.bank-statements');
        Route::get('extratos/detalhado', [BankLedgerReportPageController::class, 'index'])->name('relatorios.bank-ledger');
        Route::get('geral-analitico', [GeneralAnalyticReportPageController::class, 'index'])->name('relatorios.general-analytic');
        Route::get('contratos', ContratosReportPageController::class)->name('relatorios.contratos');
        Route::get('imoveis', ImoveisReportPageController::class)->name('relatorios.imoveis');
        Route::get('extratos/conta', [BankAccountStatementReportPageController::class, 'index'])->name('relatorios.bank-account-statement');
        Route::get('extratos/receitas', [RevenueLedgerReportPageController::class, 'index'])->name('relatorios.bank-ledger-receitas');
        Route::get('extratos/detalhado/preview', function (ReportBankLedgerFilterRequest $request) {
            $request->merge([
                'format' => 'pdf',
                'preview' => true,
            ]);

            return app(ReportBankLedgerController::class)->export($request);
        })->name('relatorios.bank-ledger.preview');
        Route::get('operacional', [OperacionalReportPageController::class, 'index'])->name('relatorios.operacional');
        Route::get('pessoas', [PessoasReportPageController::class, 'index'])->name('relatorios.pessoas');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:admin.access')->group(function () {
        Route::get('/', AdminDashboardPageController::class)->name('dashboard');
        Route::get('/usuarios', AdminUserPageController::class)->name('users.index');
        Route::get('/roles', AdminRolePageController::class)->name('roles.index');
        Route::get('/portal/locatarios', \App\Http\Controllers\Admin\PortalTenantPageController::class)->name('portal.tenants');
    });
});

$portalDomain = config('app.portal_domain');

if (! empty($portalDomain)) {
    Route::group([
        'domain' => $portalDomain,
        'middleware' => ['auth', 'tenant'],
    ], function (): void {
        Route::redirect('/', '/dashboard')->name('portal.home');
        Route::inertia('/dashboard', 'Portal/Invoices')->name('portal.dashboard');
        Route::redirect('/faturas', '/dashboard');

        Route::get('/api/contratos', [PortalContratoController::class, 'index'])->name('portal.contracts.index');
        Route::get('/api/faturas', [PortalFaturaController::class, 'index'])->name('portal.invoices.index');
        Route::get('/api/faturas/{fatura}', [PortalFaturaController::class, 'show'])->name('portal.invoices.show');
        Route::get('/faturas/{fatura}/recibo', [PortalFaturaController::class, 'receipt'])->name('portal.invoices.receipt');
    });
}

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
