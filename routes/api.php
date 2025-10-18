<?php

use App\Http\Controllers\Admin\AdminDashboardController as AdminDashboardApiController;
use App\Http\Controllers\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AlertHistoryController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\DashboardWidgetPreferenceController;
use App\Http\Controllers\Api\EntityAuditController;
use App\Http\Controllers\Api\FaturaAttachmentController;
use App\Http\Controllers\Api\FaturaController;
use App\Http\Controllers\Api\Financeiro\CostCenterController;
use App\Http\Controllers\Api\Financeiro\FinancialAccountController;
use App\Http\Controllers\Api\Financeiro\FinancialTransactionController;
use App\Http\Controllers\Api\Financeiro\PaymentScheduleController;
use App\Http\Controllers\Api\CondominioController;
use App\Http\Controllers\Api\ImovelController;
use App\Http\Controllers\Api\PessoaController;
use App\Http\Controllers\Api\Reports\ReportFinanceiroController;
use App\Http\Controllers\Api\Reports\ReportOperacionalController;
use App\Http\Controllers\Api\Reports\ReportPessoasController;
use Illuminate\Support\Facades\Route;

// Rotas gerais da API (prefixo /api aplicado automaticamente pelo Laravel)
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('imoveis/generate-codigo', [ImovelController::class, 'generateCodigo'])->name('imoveis.generate-codigo');
    Route::get('imoveis/{imovel}/fotos/download', [ImovelController::class, 'downloadPhotos'])->name('imoveis.fotos.download');
    Route::apiResource('imoveis', ImovelController::class)->parameters(['imoveis' => 'imovel']);
    Route::apiResource('pessoas', PessoaController::class);
    Route::apiResource('condominios', CondominioController::class);
    Route::get('contratos/generate-codigo', [ContratoController::class, 'generateCodigo'])->name('contratos.generate-codigo');
    Route::apiResource('contratos', ContratoController::class);
    Route::get('faturas/eligible-contracts', [FaturaController::class, 'eligibleContracts'])->name('faturas.eligible-contracts');
    Route::post('faturas/generate-month', [FaturaController::class, 'generateCurrentMonth'])->name('faturas.generate-month');
    Route::apiResource('faturas', FaturaController::class);
    Route::post('faturas/{fatura}/settle', [FaturaController::class, 'settle'])->name('faturas.settle');
    Route::post('faturas/{fatura}/cancel', [FaturaController::class, 'cancel'])->name('faturas.cancel');
    Route::post('faturas/{fatura}/email', [FaturaController::class, 'sendEmail'])->name('faturas.email');
    Route::post('faturas/{fatura}/attachments', [FaturaAttachmentController::class, 'store'])->name('faturas.attachments.store');
    Route::delete('faturas/{fatura}/attachments/{attachment}', [FaturaAttachmentController::class, 'destroy'])->name('faturas.attachments.destroy');
    Route::patch('faturas/{fatura}/attachments/{attachment}/rename', [FaturaAttachmentController::class, 'rename'])->name('faturas.attachments.rename');
    Route::patch('faturas/{fatura}/attachments/{attachment}/reset-name', [FaturaAttachmentController::class, 'resetName'])->name('faturas.attachments.reset-name');
    Route::patch('faturas/{fatura}/contrato-forma-pagamento', [FaturaController::class, 'updateContractPaymentMethod'])->name('faturas.contract-payment-method');

    Route::post('alerts/dismiss', [AlertController::class, 'dismiss'])->name('alerts.dismiss');
    Route::get('alerts/history', [AlertHistoryController::class, 'index'])->name('alerts.history.index');
    Route::post('alerts/history/{dashboard_alert}/resolve', [AlertHistoryController::class, 'resolve'])->name('alerts.history.resolve');
    Route::post('dashboard/widgets', [DashboardWidgetPreferenceController::class, 'update'])->name('dashboard.widgets.update');

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

    Route::prefix('admin')->as('admin.')->middleware('can:admin.access')->group(function () {
        Route::get('dashboard', AdminDashboardApiController::class)->name('dashboard');
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/send-reset-link', [AdminUserController::class, 'sendResetLink'])->name('users.send-reset-link');

        Route::get('roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [AdminRoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('permissions', AdminPermissionController::class)->name('permissions.index');
    });

    Route::prefix('imoveis/{imovel}/audit')->as('imoveis.audit.')->group(function () {
        Route::get('/', [EntityAuditController::class, 'imovelTimeline'])->name('index');
        Route::get('export', [EntityAuditController::class, 'imovelExport'])->name('export');
    });

    Route::prefix('contratos/{contrato}/audit')->as('contratos.audit.')->group(function () {
        Route::get('/', [EntityAuditController::class, 'contratoTimeline'])->name('index');
        Route::get('export', [EntityAuditController::class, 'contratoExport'])->name('export');
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
