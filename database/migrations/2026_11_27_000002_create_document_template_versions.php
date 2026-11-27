<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_formatos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('tipo', 24);
            $tabla->json('configuracion');
            $tabla->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamp('created_at');
            $tabla->index(['tipo', 'id']);
        });
        Schema::create('documento_versiones_emitidas', function (Blueprint $tabla) {
            $tabla->string('tipo', 24);
            $tabla->string('registro', 64);
            $tabla->foreignId('formato_id')->nullable()->constrained('documento_formatos')->restrictOnDelete();
            $tabla->timestamp('created_at');
            $tabla->primary(['tipo', 'registro']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Conserva las versiones de formatos asociadas a documentos emitidos.');
    }
};
