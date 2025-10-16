<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->string('cep', 20)->nullable()->after('telefone');
            $table->string('estado', 2)->nullable()->after('cep');
            $table->string('cidade', 120)->nullable()->after('estado');
            $table->string('bairro', 120)->nullable()->after('cidade');
            $table->string('rua', 150)->nullable()->after('bairro');
            $table->string('numero', 20)->nullable()->after('rua');
            $table->string('complemento', 150)->nullable()->after('numero');

            $table->index(['cidade', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->dropIndex(['cidade', 'estado']);
            $table->dropColumn(['cep', 'estado', 'cidade', 'bairro', 'rua', 'numero', 'complemento']);
        });
    }
};

