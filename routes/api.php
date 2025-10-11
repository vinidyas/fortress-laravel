<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\FaturaController;
use App\Http\Controllers\Api\Financeiro\CostCenterController;
use App\Http\Controllers\Api\Financeiro\FinancialAccountController;
use App\Http\Controllers\Api\Financeiro\FinancialTransactionController;
use App\Http\Controllers\Api\Financeiro\PaymentScheduleController;
use App\Http\Controllers\Api\ImovelController;
use App\Http\Controllers\Api\PessoaController;
use App\Http\Controllers\Api\Reports\ReportFinanceiroController;
use App\Http\Controllers\Api\Reports\ReportOperacionalController;
use App\Http\Controllers\Api\Reports\ReportPessoasController;
use Illuminate\Support\Facades\Route;

// Rotas gerais da API (prefixo /api aplicado automaticamente pelo Laravel)
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('imoveis', ImovelController::class)->parameters(['imoveis' => 'imovel']);
    Route::apiResource('pessoas', PessoaController::class);
    Route::apiResource('contratos', ContratoController::class);
    Route::apiResource('faturas', FaturaController::class);
    Route::post('faturas/{fatura}/settle', [FaturaController::class, 'settle'])->name('faturas.settle');
    Route::post('faturas/{fatura}/cancel', [FaturaController::class, 'cancel'])->name('faturas.cancel');

    Route::get('auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
    Route::get('auditoria/export', [AuditLogController::class, 'export'])->name('auditoria.export');

    Route::prefix('reports')->as('reports.')->group(function () {
        Route::get('financeiro', [ReportFinanceiroController::class, 'index'])->name('financeiro.index');
        Route::get('financeiro/export', [ReportFinanceiroController::class, 'export'])->name('financeiro.export');
        Route::get('operacional', [ReportOperacionalController::class, 'index'])->name('operacional.index');
        Route::get('operacional/export', [ReportOperacionalController::class, 'export'])->name('operacional.export');
        Route::get('pessoas', [ReportPessoasController::class, 'index'])->name('pessoas.index');
        Route::get('pessoas/export', [ReportPessoasController::class, 'export'])->name('pessoas.export');
    });
});

// Rotas do módulo Financeiro sob /api/financeiro
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('financeiro')->as('financeiro.')->group(function () {
    Route::apiResource('accounts', FinancialAccountController::class);

    Route::get('cost-centers/tree', [CostCenterController::class, 'tree'])->name('cost-centers.tree');
    Route::get('cost-centers/export', [CostCenterController::class, 'export'])->name('cost-centers.export');
    Route::post('cost-centers/import', [CostCenterController::class, 'import'])->name('cost-centers.import');
    Route::apiResource('cost-centers', CostCenterController::class);

    Route::get('transactions/export', [FinancialTransactionController::class, 'export'])->name('transactions.export');
    Route::apiResource('transactions', FinancialTransactionController::class);
    Route::post('transactions/{transaction}/reconcile', [FinancialTransactionController::class, 'reconcile'])->name('transactions.reconcile');
    Route::post('transactions/{transaction}/cancel', [FinancialTransactionController::class, 'cancel'])->name('transactions.cancel');
    Route::apiResource('payment-schedules', PaymentScheduleController::class);
});
