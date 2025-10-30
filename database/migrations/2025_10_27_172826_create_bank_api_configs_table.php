<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_api_configs', function (Blueprint $table) {
            $table->id();
            $table->string('bank_code', 40);
            $table->string('environment', 20)->default('sandbox');
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('certificate_password')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->json('settings')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['bank_code', 'environment']);
            $table->index(['bank_code', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_api_configs');
    }
};
