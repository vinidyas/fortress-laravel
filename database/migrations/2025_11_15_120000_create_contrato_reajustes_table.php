<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_reajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
            $table->string('indice', 50)->nullable();
            $table->decimal('percentual_aplicado', 8, 4);
            $table->decimal('valor_anterior', 15, 2);
            $table->decimal('valor_novo', 15, 2);
            $table->decimal('valor_reajuste', 15, 2);
            $table->decimal('teto_percentual', 8, 4)->nullable();
            $table->date('data_base_reajuste')->nullable();
            $table->date('data_proximo_reajuste_anterior')->nullable();
            $table->date('data_proximo_reajuste_novo')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['contrato_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_reajustes');
    }
};
