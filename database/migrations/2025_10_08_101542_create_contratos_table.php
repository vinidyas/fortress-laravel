<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_contrato', 30)->unique();
            $table->foreignId('imovel_id')->constrained('imoveis')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('locador_id')->constrained('pessoas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('locatario_id')->constrained('pessoas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('fiador_id')->nullable()->constrained('pessoas')->cascadeOnUpdate()->nullOnDelete();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->unsignedTinyInteger('dia_vencimento');
            $table->decimal('valor_aluguel', 12, 2);
            $table->string('reajuste_indice', 20)->default('IGPM');
            $table->date('data_proximo_reajuste')->nullable();
            $table->enum('garantia_tipo', ['Fiador', 'Seguro', 'Caucao', 'SemGarantia'])->default('SemGarantia');
            $table->decimal('caucao_valor', 12, 2)->nullable();
            $table->decimal('taxa_adm_percentual', 5, 2)->nullable();
            $table->enum('status', ['Ativo', 'Suspenso', 'Encerrado'])->default('Ativo');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['imovel_id', 'status']);
            $table->index('data_inicio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
