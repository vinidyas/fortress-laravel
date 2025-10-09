<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Financeiro\FinancialAccountController;
use App\Http\Controllers\Api\Financeiro\CostCenterController;
use App\Http\Controllers\Api\Financeiro\FinancialTransactionController;
use App\Http\Controllers\Api\Financeiro\PaymentScheduleController;

Route::middleware('auth:sanctum')->prefix('financeiro')->as('financeiro.')->group(function () {
    Route::apiResource('accounts', FinancialAccountController::class);
    Route::apiResource('cost-centers', CostCenterController::class);
    Route::get('transactions/export', [FinancialTransactionController::class, 'export'])->name('transactions.export');
    Route::apiResource('transactions', FinancialTransactionController::class);
    Route::post('transactions/{transaction}/reconcile', [FinancialTransactionController::class, 'reconcile'])->name('transactions.reconcile');
    Route::post('transactions/{transaction}/cancel', [FinancialTransactionController::class, 'cancel'])->name('transactions.cancel');
    Route::apiResource('payment-schedules', PaymentScheduleController::class);
    // adicione aqui outras rotas de financeiro que já existiam sob /api/financeiro/*
});
