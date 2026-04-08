<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operacion_detalles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('operacion_id');
            $table->string('producto_codigo', 50);
            $table->integer('cantidad');

            $table->timestamps();

            $table->foreign('operacion_id')
                ->references('id')->on('operaciones')
                ->cascadeOnDelete();

            $table->index('operacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacion_detalles');
    }
};