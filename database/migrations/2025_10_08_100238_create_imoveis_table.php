<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonDefault = $this->jsonArrayExpression();

        Schema::create('imoveis', function (Blueprint $table) use ($jsonDefault) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->foreignId('proprietario_id')->constrained('pessoas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('agenciador_id')->nullable()->constrained('pessoas')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('responsavel_id')->nullable()->constrained('pessoas')->cascadeOnUpdate()->nullOnDelete();
            $table->string('tipo_imovel', 120);
            $table->json('finalidade')->default($jsonDefault);
            $table->enum('disponibilidade', ['Disponivel', 'Indisponivel'])->default('Disponivel');
            $table->string('cep', 20)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cidade', 120)->nullable();
            $table->string('bairro', 120)->nullable();
            $table->string('rua', 150)->nullable();
            $table->foreignId('condominio_id')->nullable()->constrained('condominios')->cascadeOnUpdate()->nullOnDelete();
            $table->string('logradouro', 150)->nullable();
            $table->string('numero', 20);
            $table->string('complemento', 150)->nullable();
            $table->decimal('valor_locacao', 12, 2)->nullable();
            $table->decimal('valor_condominio', 12, 2)->nullable();
            $table->boolean('condominio_isento')->default(false);
            $table->decimal('valor_iptu', 12, 2)->nullable();
            $table->boolean('iptu_isento')->default(false);
            $table->decimal('outros_valores', 12, 2)->nullable();
            $table->boolean('outros_isento')->default(false);
            $table->enum('periodo_iptu', ['Mensal', 'Anual'])->default('Mensal');
            $table->unsignedTinyInteger('dormitorios')->nullable();
            $table->unsignedTinyInteger('suites')->nullable();
            $table->unsignedTinyInteger('banheiros')->nullable();
            $table->unsignedTinyInteger('vagas_garagem')->nullable();
            $table->decimal('area_total', 12, 2)->nullable();
            $table->decimal('area_construida', 12, 2)->nullable();
            $table->json('comodidades')->default($jsonDefault);
            $table->timestamps();

            $table->index(['cidade', 'bairro']);
            $table->index('disponibilidade');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imoveis');
    }

    private function jsonArrayExpression(): Expression|string
    {
        return Schema::getConnection()->getDriverName() === 'mysql'
            ? DB::raw('(JSON_ARRAY())')
            : '[]';
    }
};
