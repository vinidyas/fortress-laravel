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
        Schema::create('imovel_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imovel_id')
                ->constrained('imoveis')
                ->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->string('legenda', 255)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();

            $table->index(['imovel_id', 'ordem']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imovel_fotos');
    }
};
