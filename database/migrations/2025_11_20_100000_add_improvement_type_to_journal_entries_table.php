<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'improvement_type')) {
                $table->string('improvement_type', 20)->nullable()->after('reference_code');
                $table->index('improvement_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'improvement_type')) {
                $table->dropIndex('journal_entries_improvement_type_index');
                $table->dropColumn('improvement_type');
            }
        });
    }
};
