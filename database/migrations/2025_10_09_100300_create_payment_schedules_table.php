<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->decimal('valor_total', 15, 2);
            $table->unsignedInteger('parcela_atual')->default(0);
            $table->unsignedInteger('total_parcelas')->default(1);
            $table->date('vencimento');
            $table->enum('status', ['aberto', 'quitado', 'em_atraso', 'cancelado'])->default('aberto');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['vencimento', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
