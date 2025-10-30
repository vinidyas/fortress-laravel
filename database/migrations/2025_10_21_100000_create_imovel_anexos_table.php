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
        Schema::create('imovel_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imovel_id')->constrained('imoveis')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('display_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['imovel_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imovel_anexos');
    }
};
