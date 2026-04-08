<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operaciones', function (Blueprint $table) {
            $table->id();

            $table->string('tipo');   // TRASLADO
            $table->string('estado'); // PENDIENTE | APROBADO | RECHAZADO

            $table->unsignedBigInteger('bodega_origen_id');
            $table->unsignedBigInteger('bodega_destino_id');

            $table->unsignedBigInteger('creado_por');

            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->dateTime('aprobado_en')->nullable();

            $table->unsignedBigInteger('rechazado_por')->nullable();
            $table->dateTime('rechazado_en')->nullable();
            $table->text('motivo_rechazo')->nullable();

            $table->text('observacion')->nullable();

            $table->timestamps();

            $table->foreign('bodega_origen_id')->references('id')->on('bodegas');
            $table->foreign('bodega_destino_id')->references('id')->on('bodegas');

            $table->foreign('creado_por')->references('id')->on('users');
            $table->foreign('aprobado_por')->references('id')->on('users');
            $table->foreign('rechazado_por')->references('id')->on('users');

            $table->index(['tipo', 'estado']);
            $table->index(['bodega_destino_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones');
    }
};