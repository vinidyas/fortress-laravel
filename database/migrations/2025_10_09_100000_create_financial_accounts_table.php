<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->enum('tipo', ['conta_corrente', 'caixa', 'outro'])->default('conta_corrente');
            $table->string('banco', 120)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('numero', 40)->nullable();
            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
