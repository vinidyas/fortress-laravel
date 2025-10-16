<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('usuarios')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('widget_key', 60);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'widget_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_widgets');
    }
};
