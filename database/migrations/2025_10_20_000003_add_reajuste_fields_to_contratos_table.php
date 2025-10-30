<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'reajuste_indice_outro')) {
                $table->string('reajuste_indice_outro', 60)->nullable()->after('reajuste_indice');
            }

            if (! Schema::hasColumn('contratos', 'reajuste_teto_percentual')) {
                $table->decimal('reajuste_teto_percentual', 5, 2)->nullable()->after('reajuste_periodicidade_meses');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'reajuste_indice_outro')) {
                $table->dropColumn('reajuste_indice_outro');
            }

            if (Schema::hasColumn('contratos', 'reajuste_teto_percentual')) {
                $table->dropColumn('reajuste_teto_percentual');
            }
        });
    }
};
