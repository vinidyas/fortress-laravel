<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('usuarios')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('alert_key', 160);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'alert_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_alerts');
    }
};
