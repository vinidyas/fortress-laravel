<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('competencia');
            $table->date('vencimento')->nullable();
            $table->enum('status', ['Aberta', 'Paga', 'Cancelada'])->default('Aberta');
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->decimal('valor_pago', 12, 2)->nullable();
            $table->date('pago_em')->nullable();
            $table->string('metodo_pagamento', 30)->nullable();
            $table->string('nosso_numero', 50)->nullable();
            $table->string('boleto_url', 255)->nullable();
            $table->text('pix_qrcode')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['contrato_id', 'competencia']);
            $table->index('status');
            $table->index('vencimento');
            $table->index('competencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
    }
};
