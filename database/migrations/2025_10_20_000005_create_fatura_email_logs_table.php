<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fatura_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('subject');
            $table->json('recipients');
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_email_logs');
    }
};
