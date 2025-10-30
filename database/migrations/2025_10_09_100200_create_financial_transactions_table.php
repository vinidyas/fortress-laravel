<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('fatura_id')->nullable()->constrained('faturas')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('parent_transaction_id')->nullable()->constrained('financial_transactions')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('tipo', ['credito', 'debito']);
            $table->decimal('valor', 15, 2);
            $table->date('data_ocorrencia');
            $table->string('descricao', 255)->nullable();
            $table->enum('status', ['pendente', 'conciliado', 'cancelado'])->default('pendente');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['data_ocorrencia', 'status']);
            $table->index(['account_id', 'cost_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
