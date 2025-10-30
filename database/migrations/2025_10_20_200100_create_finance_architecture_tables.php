<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->enum('tipo', ['receita', 'despesa', 'transferencia']);
            $table->boolean('is_investment')->default(false);
            $table->boolean('is_renovation')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['tipo', 'ativo']);
        });

        Schema::create('journal_entry_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('texto', 255)->unique();
            $table->unsignedInteger('uso_total')->default(0);
            $table->timestamp('ultima_utilizacao')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['receita', 'despesa', 'transferencia']);
            $table->foreignId('bank_account_id')->constrained('financial_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('counter_bank_account_id')->nullable()->constrained('financial_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('financial_category_id')->nullable()->constrained('financial_categories')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('imoveis')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('pessoas')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('description_id')->nullable()->constrained('journal_entry_descriptions')->cascadeOnUpdate()->nullOnDelete();
            $table->string('description_custom', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_code', 40)->nullable();
            $table->enum('origin', ['manual', 'importado', 'recorrente', 'parcelado', 'clonado', 'integracao'])->default('manual');
            $table->foreignId('clone_of_id')->nullable()->constrained('journal_entries')->cascadeOnUpdate()->nullOnDelete();
            $table->date('movement_date');
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('BRL');
            $table->enum('status', ['planejado', 'pendente', 'atrasado', 'pago', 'cancelado'])->default('planejado');
            $table->unsignedSmallInteger('installments_count')->default(1);
            $table->unsignedSmallInteger('paid_installments')->default(0);
            $table->unsignedSmallInteger('attachments_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bank_account_id', 'movement_date']);
            $table->index(['status', 'movement_date']);
            $table->index(['person_id', 'property_id']);
            $table->index('origin');
            $table->index('reference_code');
        });

        Schema::create('journal_entry_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_parcela');
            $table->date('movement_date');
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->decimal('valor_principal', 15, 2);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_multa', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2);
            $table->enum('status', ['planejado', 'pendente', 'pago', 'cancelado', 'atrasado'])->default('planejado');
            $table->foreignId('paid_by_installment_id')->nullable()->constrained('journal_entry_installments')->cascadeOnUpdate()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'numero_parcela'], 'je_installments_entry_number_unique');
            $table->index(['due_date', 'status']);
        });

        Schema::create('journal_entry_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('imoveis')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('percentage', 6, 3)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'cost_center_id', 'property_id'], 'journal_entry_allocation_unique');
        });

        Schema::create('journal_entry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('journal_entry_installments')->cascadeOnUpdate()->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('journal_entry_installments')->cascadeOnUpdate()->nullOnDelete();
            $table->string('number', 40);
            $table->date('issue_date');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->string('pdf_path');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'installment_id', 'number'], 'financial_receipts_unique_number');
        });

        Schema::create('financial_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2);
            $table->enum('status', ['aberto', 'em_conferencia', 'fechado'])->default('aberto');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamps();

            $table->unique(['financial_account_id', 'period_start', 'period_end'], 'financial_reconciliations_period_unique');
        });

        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('reference', 60);
            $table->string('original_name', 120);
            $table->timestamp('imported_at');
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->string('hash', 64);
            $table->enum('status', ['processando', 'importado', 'conciliado', 'erro'])->default('processando');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['financial_account_id', 'hash']);
            $table->index(['financial_account_id', 'status']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('linha');
            $table->date('transaction_date');
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance', 15, 2)->nullable();
            $table->string('document_number', 60)->nullable();
            $table->string('fit_id', 80)->nullable();
            $table->enum('match_status', ['nao_casado', 'sugerido', 'confirmado', 'ignorado'])->default('nao_casado');
            $table->foreignId('matched_installment_id')->nullable()->constrained('journal_entry_installments')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedBigInteger('matched_by')->nullable();
            $table->json('match_meta')->nullable();
            $table->timestamps();

            $table->unique(['bank_statement_id', 'linha']);
            $table->index(['match_status', 'transaction_date']);
        });

        Schema::create('bank_statement_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_line_id')->constrained('bank_statement_lines')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('journal_entry_installments')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('matched_at');
            $table->unsignedBigInteger('matched_by')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['matched_by', 'matched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_matches');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('financial_reconciliations');
        Schema::dropIfExists('financial_receipts');
        Schema::dropIfExists('journal_entry_attachments');
        Schema::dropIfExists('journal_entry_allocations');
        Schema::dropIfExists('journal_entry_installments');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_entry_descriptions');
        Schema::dropIfExists('financial_categories');
    }
};
