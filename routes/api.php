<?php

use App\Http\Controllers\Admin\AdminDashboardController as AdminDashboardApiController;
use App\Http\Controllers\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AlertHistoryController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CondominioController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\ContratoReajusteController;
use App\Http\Controllers\Api\DashboardWidgetPreferenceController;
use App\Http\Controllers\Api\EntityAuditController;
use App\Http\Controllers\Api\FaturaAttachmentController;
use App\Http\Controllers\Api\FaturaBoletoController;
use App\Http\Controllers\Api\FaturaController;
use App\Http\Controllers\Api\Financeiro\AccountBalanceController;
use App\Http\Controllers\Api\Financeiro\BankStatementController;
use App\Http\Controllers\Api\Financeiro\CostCenterController;
use App\Http\Controllers\Api\Financeiro\FinancialAccountBalanceController;
use App\Http\Controllers\Api\Financeiro\FinancialAccountController;
use App\Http\Controllers\Api\Financeiro\FinancialReconciliationController;
use App\Http\Controllers\Api\Financeiro\JournalEntryAttachmentController;
use App\Http\Controllers\Api\Financeiro\JournalEntryController;
use App\Http\Controllers\Api\Financeiro\JournalEntryDescriptionController;
use App\Http\Controllers\Api\Financeiro\JournalEntryReceiptController;
use App\Http\Controllers\Api\Financeiro\PaymentScheduleController;
use App\Http\Controllers\Api\ImovelController;
use App\Http\Controllers\Api\PessoaController;
use App\Http\Controllers\Admin\PortalTenantUserController;
use App\Http\Controllers\Api\Reports\BankAccountStatementController;
use App\Http\Controllers\Api\Reports\ReportBankLedgerController;
use App\Http\Controllers\Api\Reports\ReportBankStatementController;
use App\Http\Controllers\Api\Reports\ReportFinanceiroController;
use App\Http\Controllers\Api\Reports\ReportGeneralAnalyticController;
use App\Http\Controllers\Api\Reports\ReportOperacionalController;
use App\Http\Controllers\Api\Reports\ReportPessoasController;
use App\Http\Controllers\Webhooks\BradescoWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('imoveis/generate-codigo', [ImovelController::class, 'generateCodigo'])->name('imoveis.generate-codigo');
    Route::get('imoveis/{imovel}/fotos/download', [ImovelController::class, 'downloadPhotos'])->name('imoveis.fotos.download');
    Route::apiResource('imoveis', ImovelController::class)
        ->parameters(['imoveis' => 'imovel'])
        ->names([
            'index'   => 'api.imoveis.index',
            'store'   => 'api.imoveis.store',
            'show'    => 'api.imoveis.show',
            'update'  => 'api.imoveis.update',
            'destroy' => 'api.imoveis.destroy',
        ]);

    Route::apiResource('pessoas', PessoaController::class)->names([
        'index'   => 'api.pessoas.index',
        'store'   => 'api.pessoas.store',
        'show'    => 'api.pessoas.show',
        'update'  => 'api.pessoas.update',
        'destroy' => 'api.pessoas.destroy',
    ]);

    Route::apiResource('condominios', CondominioController::class)->names([
        'index'   => 'api.condominios.index',
        'store'   => 'api.condominios.store',
        'show'    => 'api.condominios.show',
        'update'  => 'api.condominios.update',
        'destroy' => 'api.condominios.destroy',
    ]);

    Route::get('contratos/generate-codigo', [ContratoController::class, 'generateCodigo'])->name('contratos.generate-codigo');
    Route::apiResource('contratos', ContratoController::class)->names([
        'index'   => 'api.contratos.index',
        'store'   => 'api.contratos.store',
        'show'    => 'api.contratos.show',
        'update'  => 'api.contratos.update',
        'destroy' => 'api.contratos.destroy',
    ]);
    Route::post('contratos/{contrato}/reajustes', [ContratoReajusteController::class, 'store'])->name('contratos.reajustes.store');
    Route::get('contratos/{contrato}/audit', [EntityAuditController::class, 'contratoTimeline'])->name('contratos.audit.index');
    Route::get('contratos/{contrato}/audit/export', [EntityAuditController::class, 'exportContratoTimeline'])->name('contratos.audit.export');

    Route::get('faturas/eligible-contracts', [FaturaController::class, 'eligibleContracts'])->name('faturas.eligible-contracts');
    Route::post('faturas/generate-month', [FaturaController::class, 'generateCurrentMonth'])->name('faturas.generate-month');
    Route::apiResource('faturas', FaturaController::class)->names([
        'index'   => 'api.faturas.index',
        'store'   => 'api.faturas.store',
        'show'    => 'api.faturas.show',
        'update'  => 'api.faturas.update',
        'destroy' => 'api.faturas.destroy',
    ]);
    Route::post('faturas/{fatura}/settle', [FaturaController::class, 'settle'])->name('faturas.settle');
    Route::post('faturas/{fatura}/cancel', [FaturaController::class, 'cancel'])->name('faturas.cancel');
    Route::post('faturas/{fatura}/email', [FaturaController::class, 'sendEmail'])->name('faturas.email');
    Route::get('faturas/{fatura}/boletos', [FaturaBoletoController::class, 'index'])->name('faturas.boletos.index');
    Route::post('faturas/{fatura}/boletos', [FaturaBoletoController::class, 'store'])->name('faturas.boletos.store');
    Route::get('faturas/{fatura}/boletos/{boleto}', [FaturaBoletoController::class, 'show'])->name('faturas.boletos.show');
    Route::post('faturas/{fatura}/attachments', [FaturaAttachmentController::class, 'store'])->name('faturas.attachments.store');
    Route::delete('faturas/{fatura}/attachments/{attachment}', [FaturaAttachmentController::class, 'destroy'])->name('faturas.attachments.destroy');
    Route::patch('faturas/{fatura}/attachments/{attachment}/rename', [FaturaAttachmentController::class, 'rename'])->name('faturas.attachments.rename');
    Route::patch('faturas/{fatura}/attachments/{attachment}/reset-name', [FaturaAttachmentController::class, 'resetName'])->name('faturas.attachments.reset-name');
    Route::patch('faturas/{fatura}/contrato-forma-pagamento', [FaturaController::class, 'updateContractPaymentMethod'])->name('faturas.contract-payment-method');

    Route::post('alerts/dismiss', [AlertController::class, 'dismiss'])->name('alerts.dismiss');
    Route::get('alerts/history', [AlertHistoryController::class, 'index'])->name('alerts.history.index');
    Route::post('alerts/history/{dashboard_alert}/resolve', [AlertHistoryController::class, 'resolve'])->name('alerts.history.resolve');
    Route::post('dashboard/widgets', [DashboardWidgetPreferenceController::class, 'update'])->name('dashboard.widgets.update');

    Route::get('auditoria', [AuditLogController::class, 'index'])->name('api.auditoria.index');
    Route::get('auditoria/export', [AuditLogController::class, 'export'])->name('api.auditoria.export');

    Route::patch('financial-accounts/{account}/initial-balance', [FinancialAccountBalanceController::class, 'update'])->name('financial-accounts.initial-balance.update');
    Route::get('financeiro/account-balances', AccountBalanceController::class)->name('financeiro.account-balances');

    Route::prefix('reports')->as('reports.')->group(function () {
        Route::get('financeiro', [ReportFinanceiroController::class, 'index'])->name('financeiro.index');
        Route::get('financeiro/export', [ReportFinanceiroController::class, 'export'])->name('financeiro.export');
        Route::get('bank-statements', [ReportBankStatementController::class, 'index'])->name('bank-statements.index');
        Route::get('bank-statements/export', [ReportBankStatementController::class, 'export'])->name('bank-statements.export');
        Route::get('bank-ledger', [ReportBankLedgerController::class, 'index'])->name('bank-ledger.index');
        Route::get('bank-ledger/export', [ReportBankLedgerController::class, 'export'])->name('bank-ledger.export');
        Route::get('general-analytic', [ReportGeneralAnalyticController::class, 'index'])->name('general-analytic.index');
        Route::get('general-analytic/export', [ReportGeneralAnalyticController::class, 'export'])->name('general-analytic.export');
        Route::get('bank-account-statement', [BankAccountStatementController::class, 'index'])->name('bank-account-statement.index');
        Route::get('operacional', [ReportOperacionalController::class, 'index'])->name('operacional.index');
        Route::get('operacional/export', [ReportOperacionalController::class, 'export'])->name('operacional.export');
        Route::get('pessoas', [ReportPessoasController::class, 'index'])->name('pessoas.index');
        Route::get('pessoas/export', [ReportPessoasController::class, 'export'])->name('pessoas.export');
    });

    Route::prefix('admin')->as('api.admin.')->middleware('can:admin.access')->group(function () {
        Route::post('portal/tenant-users', [PortalTenantUserController::class, 'store'])->name('portal.tenant-users.store');
        Route::get('portal/locatarios', [PortalTenantUserController::class, 'index'])->name('portal.tenants.index');
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
});

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('financeiro')->as('financeiro.')->group(function () {
    Route::apiResource('accounts', FinancialAccountController::class)->names([
        'index'   => 'financeiro.accounts.index',
        'store'   => 'financeiro.accounts.store',
        'show'    => 'financeiro.accounts.show',
        'update'  => 'financeiro.accounts.update',
        'destroy' => 'financeiro.accounts.destroy',
    ]);

    Route::get('cost-centers/tree', [CostCenterController::class, 'tree'])->name('cost-centers.tree');
    Route::get('cost-centers/export', [CostCenterController::class, 'export'])->name('cost-centers.export');
    Route::post('cost-centers/import', [CostCenterController::class, 'import'])->name('cost-centers.import');
    Route::apiResource('cost-centers', CostCenterController::class)->names([
        'index'   => 'financeiro.cost-centers.index',
        'store'   => 'financeiro.cost-centers.store',
        'show'    => 'financeiro.cost-centers.show',
        'update'  => 'financeiro.cost-centers.update',
        'destroy' => 'financeiro.cost-centers.destroy',
    ]);

    Route::apiResource('payment-schedules', PaymentScheduleController::class)->names([
        'index'   => 'financeiro.payment-schedules.index',
        'store'   => 'financeiro.payment-schedules.store',
        'show'    => 'financeiro.payment-schedules.show',
        'update'  => 'financeiro.payment-schedules.update',
        'destroy' => 'financeiro.payment-schedules.destroy',
    ]);

    Route::get('journal-entries/export', [JournalEntryController::class, 'export'])->name('journal-entries.export');
    Route::get('journal-entry-descriptions', JournalEntryDescriptionController::class)->name('journal-entry-descriptions.index');
    Route::post('journal-entries/{journal_entry}/clone', [JournalEntryController::class, 'clone'])->name('journal-entries.clone');
    Route::post('journal-entries/{journal_entry}/installments/{installment}/pay', [JournalEntryController::class, 'payInstallment'])->name('journal-entries.installments.pay');
    Route::post('journal-entries/{journal_entry}/cancel', [JournalEntryController::class, 'cancel'])->name('journal-entries.cancel');
    Route::post('journal-entries/{journal_entry}/generate-receipt', [JournalEntryController::class, 'generateReceipt'])->name('journal-entries.generate-receipt');
    Route::get('journal-entries/{journal_entry}/attachments', [JournalEntryAttachmentController::class, 'index'])->name('journal-entries.attachments.index');
    Route::post('journal-entries/{journal_entry}/attachments', [JournalEntryAttachmentController::class, 'store'])->name('journal-entries.attachments.store');
    Route::get('journal-entries/{journal_entry}/attachments/{attachment}/download', [JournalEntryAttachmentController::class, 'download'])->name('journal-entries.attachments.download');
    Route::delete('journal-entries/{journal_entry}/attachments/{attachment}', [JournalEntryAttachmentController::class, 'destroy'])->name('journal-entries.attachments.destroy');
    Route::get('journal-entries/{journal_entry}/receipts', [JournalEntryReceiptController::class, 'index'])->name('journal-entries.receipts.index');
    Route::get('journal-entries/{journal_entry}/receipts/{receipt}/download', [JournalEntryReceiptController::class, 'download'])->name('journal-entries.receipts.download');
    Route::apiResource('journal-entries', JournalEntryController::class)->names([
        'index'   => 'financeiro.journal-entries.index',
        'store'   => 'financeiro.journal-entries.store',
        'show'    => 'financeiro.journal-entries.show',
        'update'  => 'financeiro.journal-entries.update',
        'destroy' => 'financeiro.journal-entries.destroy',
    ]);

    Route::get('bank-statements', [BankStatementController::class, 'index'])->name('bank-statements.index');
    Route::post('bank-statements', [BankStatementController::class, 'store'])->name('bank-statements.store');
    Route::get('bank-statements/{bank_statement}', [BankStatementController::class, 'show'])->name('bank-statements.show');
    Route::post('bank-statements/{bank_statement}/suggest-matches', [BankStatementController::class, 'suggest'])->name('bank-statements.suggest-matches');
    Route::post('bank-statements/{bank_statement}/lines/{bank_statement_line}/confirm', [BankStatementController::class, 'confirmLine'])->name('bank-statements.lines.confirm');
    Route::post('bank-statements/{bank_statement}/lines/{bank_statement_line}/ignore', [BankStatementController::class, 'ignoreLine'])->name('bank-statements.lines.ignore');
    Route::delete('bank-statements/{bank_statement}', [BankStatementController::class, 'destroy'])->name('bank-statements.destroy');

    Route::get('reconciliations/export', [FinancialReconciliationController::class, 'export'])->name('reconciliations.export');
    Route::apiResource('reconciliations', FinancialReconciliationController::class)
        ->only(['index', 'show', 'store', 'destroy'])
        ->names([
            'index'   => 'financeiro.reconciliations.index',
            'store'   => 'financeiro.reconciliations.store',
            'show'    => 'financeiro.reconciliations.show',
            'destroy' => 'financeiro.reconciliations.destroy',
        ]);
});

Route::post('webhooks/bradesco', BradescoWebhookController::class)->name('webhooks.bradesco');
