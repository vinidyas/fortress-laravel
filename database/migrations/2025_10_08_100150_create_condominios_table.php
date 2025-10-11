<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condominios', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->string('cnpj', 20)->nullable();
            $table->string('cep', 20)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cidade', 120)->nullable();
            $table->string('bairro', 120)->nullable();
            $table->string('rua', 150)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 150)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('nome');
            $table->index(['cidade', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condominios');
    }
};
