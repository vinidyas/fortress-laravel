<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->supportsEnumAlter()) {
            return;
        }

        DB::statement("
            ALTER TABLE `journal_entries`
            MODIFY `status` ENUM('planejado','pendente','atrasado','pago','cancelado')
            NOT NULL DEFAULT 'planejado'
        ");

        DB::statement("
            ALTER TABLE `journal_entry_installments`
            MODIFY `status` ENUM('planejado','pendente','atrasado','pago','cancelado')
            NOT NULL DEFAULT 'planejado'
        ");
    }

    public function down(): void
    {
        if (! $this->supportsEnumAlter()) {
            return;
        }

        DB::statement("
            ALTER TABLE `journal_entries`
            MODIFY `status` ENUM('planejado','pendente','pago','cancelado')
            NOT NULL DEFAULT 'planejado'
        ");

        DB::statement("
            ALTER TABLE `journal_entry_installments`
            MODIFY `status` ENUM('planejado','pendente','pago','cancelado')
            NOT NULL DEFAULT 'planejado'
        ");
    }

    private function supportsEnumAlter(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return in_array($driver, ['mysql', 'mariadb'], true);
    }
};
