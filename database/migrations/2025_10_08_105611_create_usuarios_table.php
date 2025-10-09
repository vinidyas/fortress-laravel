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

        Schema::create('usuarios', function (Blueprint $table) use ($jsonDefault) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('nome', 120);
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->json('permissoes')->nullable()->default($jsonDefault);
            $table->boolean('ativo')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }

    private function jsonArrayExpression(): Expression|string
    {
        return Schema::getConnection()->getDriverName() === 'mysql'
            ? DB::raw('(JSON_ARRAY())')
            : '[]';
    }
};
