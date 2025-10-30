<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fatura_boletos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->string('bank_code', 40);
            $table->string('external_id', 120)->nullable();
            $table->string('nosso_numero', 50)->nullable();
            $table->string('document_number', 50)->nullable();
            $table->string('linha_digitavel', 255)->nullable();
            $table->string('codigo_barras', 255)->nullable();
            $table->decimal('valor', 12, 2);
            $table->date('vencimento');
            $table->enum('status', ['pending', 'registered', 'paid', 'canceled', 'failed'])->default('pending');
            $table->dateTime('registrado_em')->nullable();
            $table->dateTime('liquidado_em')->nullable();
            $table->decimal('valor_pago', 12, 2)->nullable();
            $table->string('pdf_url', 255)->nullable();
            $table->json('payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['fatura_id', 'bank_code', 'external_id']);
            $table->index('status');
            $table->index('vencimento');
            $table->index('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fatura_boletos');
    }
};
