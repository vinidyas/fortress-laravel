<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'multa_rescisao_alugueis')) {
                $table->decimal('multa_rescisao_alugueis', 5, 2)->default(3)->after('juros_mora_percentual_mes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'multa_rescisao_alugueis')) {
                $table->dropColumn('multa_rescisao_alugueis');
            }
        });
    }
};
