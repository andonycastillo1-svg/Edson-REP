<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();

            $table->string('mes', 20);
            $table->string('no_factura', 50);

            $table->foreignId('proveedor_id')->constrained('proveedores');

            $table->string('forma_pago', 100)->nullable();
            $table->string('proyecto', 150)->nullable();
            $table->string('solicitado_por', 150)->nullable();
            $table->string('autorizado_por', 150)->nullable();
            $table->string('a_utilizarse', 255)->nullable();

            $table->decimal('total_factura', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['proveedor_id', 'no_factura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
