<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `payment_schedules` ADD CONSTRAINT `chk_payment_schedules_parcelas` CHECK (`parcela_atual` <= `total_parcelas`)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE `payment_schedules` DROP CHECK `chk_payment_schedules_parcelas`');
            } catch (Throwable $e) {
                // ignore if not supported
            }
        }
    }
};
