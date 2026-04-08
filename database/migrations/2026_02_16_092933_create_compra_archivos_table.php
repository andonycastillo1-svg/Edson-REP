<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_archivos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('compra_id')
                  ->constrained('compras')
                  ->cascadeOnDelete();

            $table->string('ruta', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('tamano')->nullable();

            $table->timestamps();

            $table->index(['compra_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_archivos');
    }
};
