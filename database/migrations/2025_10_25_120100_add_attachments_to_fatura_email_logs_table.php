<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fatura_email_logs', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('bcc');
        });
    }

    public function down(): void
    {
        Schema::table('fatura_email_logs', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
