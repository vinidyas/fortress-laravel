<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `journal_entries` MODIFY `origin` ENUM('manual','importado','recorrente','parcelado','clonado','integracao','legacy','agendamento') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `journal_entries` MODIFY `origin` ENUM('manual','importado','recorrente','parcelado','clonado','integracao') NOT NULL DEFAULT 'manual'");
    }
};
