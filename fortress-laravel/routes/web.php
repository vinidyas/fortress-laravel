<?php

use App\Http\Controllers\AuditTrailPageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Financeiro\FinanceiroPageController;
use App\Http\Controllers\Financeiro\PaymentSchedulePageController;
use App\Http\Controllers\Financeiro\SettingsPageController;
use App\Http\Controllers\Reports\FinanceiroReportPageController;
use App\Http\Controllers\Reports\OperacionalReportPageController;
use App\Http\Controllers\Reports\PessoasReportPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/imoveis', fn () => Inertia::render('Imoveis/Index'))->name('imoveis.index');
    Route::get('/imoveis/novo', fn () => Inertia::render('Imoveis/Edit'))->name('imoveis.create');
    Route::get('/imoveis/{imovel}', fn (int $imovel) => Inertia::render('Imoveis/Edit', [
        'imovelId' => $imovel,
    ]))->name('imoveis.edit');

    Route::get('/pessoas', fn () => Inertia::render('Pessoas/Index'))->name('pessoas.index');
    Route::get('/pessoas/novo', fn () => Inertia::render('Pessoas/Edit'))->name('pessoas.create');
    Route::get('/pessoas/{pessoa}', fn (int $pessoa) => Inertia::render('Pessoas/Edit', [
        'pessoaId' => $pessoa,
    ]))->name('pessoas.edit');

    Route::get('/contratos', fn () => Inertia::render('Contratos/Index'))->name('contratos.index');
    Route::get('/contratos/novo', fn () => Inertia::render('Contratos/Edit'))->name('contratos.create');
    Route::get('/contratos/{contrato}', fn (int $contrato) => Inertia::render('Contratos/Edit', [
        'contratoId' => $contrato,
    ]))->name('contratos.edit');

    Route::get('/faturas', fn () => Inertia::render('Faturas/Index'))->name('faturas.index');
    Route::get('/faturas/novo', fn () => Inertia::render('Faturas/Show', [
        'faturaId' => null,
    ]))->name('faturas.create');
    Route::get('/faturas/{fatura}', fn (int $fatura) => Inertia::render('Faturas/Show', [
        'faturaId' => $fatura,
    ]))->name('faturas.show');

    Route::get('/financeiro', [FinanceiroPageController::class, 'index'])->name('financeiro.index');
    Route::get('/financeiro/transactions/novo', [FinanceiroPageController::class, 'create'])->name('financeiro.transactions.create');
    Route::get('/financeiro/transactions/{transaction}', [FinanceiroPageController::class, 'edit'])->name('financeiro.transactions.edit');
    Route::get('/financeiro/contas', [SettingsPageController::class, 'accounts'])->name('financeiro.accounts');
    Route::get('/financeiro/centros', [SettingsPageController::class, 'costCenters'])->name('financeiro.cost-centers');
    Route::get('/financeiro/agendamentos', [PaymentSchedulePageController::class, 'index'])->name('financeiro.payment-schedules');
    Route::get('/financeiro/agendamentos/novo', [PaymentSchedulePageController::class, 'create'])->name('financeiro.payment-schedules.create');

    Route::get('/auditoria', [AuditTrailPageController::class, 'index'])->name('auditoria.index');

    Route::prefix('relatorios')->group(function () {
        Route::get('financeiro', [FinanceiroReportPageController::class, 'index'])->name('relatorios.financeiro');
        Route::get('operacional', [OperacionalReportPageController::class, 'index'])->name('relatorios.operacional');
        Route::get('pessoas', [PessoasReportPageController::class, 'index'])->name('relatorios.pessoas');
    });
});

// Rotas de API movidas para routes/api.php

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
