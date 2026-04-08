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
       Schema::create('productos', function (Blueprint $table) {
    $table->string('codigo', 50)->primary(); // codigo_producto
    $table->string('nombre', 150);
    $table->text('descripcion')->nullable();
    $table->string('unidad_medida', 50)->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
