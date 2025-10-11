<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->after('nome');
            $table->foreignId('parent_id')->nullable()->after('codigo')->constrained('cost_centers')->nullOnDelete();
            $table->index('parent_id');
        });

        $counter = 1;
        DB::table('cost_centers')
            ->orderBy('id')
            ->lazy()
            ->each(function ($center) use (&$counter) {
                DB::table('cost_centers')
                    ->where('id', $center->id)
                    ->update(['codigo' => sprintf('%d.0', $counter++)]);
            });

        Schema::table('cost_centers', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable(false)->change();
            $table->unique('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['codigo', 'parent_id']);
        });
    }
};
