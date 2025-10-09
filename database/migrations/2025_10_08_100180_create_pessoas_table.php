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

        Schema::create('pessoas', function (Blueprint $table) use ($jsonDefault) {
            $table->id();
            $table->string('nome_razao_social', 255);
            $table->string('cpf_cnpj', 20)->unique()->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->enum('tipo_pessoa', ['Fisica', 'Juridica']);
            $table->json('papeis')->default($jsonDefault);
            $table->timestamps();

            $table->index('nome_razao_social');
            $table->index('tipo_pessoa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }

    private function jsonArrayExpression(): Expression|string
    {
        return Schema::getConnection()->getDriverName() === 'mysql'
            ? DB::raw('(JSON_ARRAY())')
            : '[]';
    }
};
