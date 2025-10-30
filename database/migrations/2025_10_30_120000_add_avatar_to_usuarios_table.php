<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('email')->nullable()->after('nome');
            $table->string('avatar_path')->nullable()->after('permissoes');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'avatar_path']);
        });
    }
};
