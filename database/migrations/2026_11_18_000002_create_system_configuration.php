<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('descripcion', 500)->nullable();
            $table->unsignedTinyInteger('ambito_operativo')->nullable();
            $table->boolean('activo')->default(true);
        });
        Schema::table('users', fn (Blueprint $table) => $table->boolean('acceso_activo')->default(true));

        Schema::create('sistema_ajustes', function (Blueprint $table) {
            $table->string('clave', 100)->primary();
            $table->json('valor');
        });
        DB::table('sistema_ajustes')->insert(['clave' => 'seguridad.mutex', 'valor' => '{}']);

        Schema::create('sistema_superadministradores', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->boolean('activo');
            $table->timestamps();
        });
        Schema::create('sistema_permisos_rol', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permiso', 100);
            $table->boolean('permitido');
            $table->primary(['role_id', 'permiso']);
        });
        Schema::create('sistema_permisos_usuario', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permiso', 100);
            $table->boolean('permitido');
            $table->primary(['user_id', 'permiso']);
        });

        Schema::create('sistema_campos', function (Blueprint $table) {
            $table->id();
            $table->string('modulo', 40);
            $table->string('clave', 60);
            $table->string('etiqueta', 100);
            $table->string('ayuda', 500)->nullable();
            $table->string('tipo', 30);
            $table->json('predeterminado')->nullable();
            $table->string('permiso_lectura', 100)->nullable();
            $table->string('permiso_edicion', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('obligatorio')->default(false);
            $table->boolean('formulario')->default(true);
            $table->boolean('tabla')->default(false);
            $table->boolean('filtro')->default(false);
            $table->boolean('exportacion')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamp('archivado_en')->nullable();
            $table->timestamps();
            $table->unique(['modulo', 'clave']);
        });
        Schema::create('sistema_campo_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campo_id')->constrained('sistema_campos')->restrictOnDelete();
            $table->string('etiqueta', 100);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });
        Schema::create('sistema_campo_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campo_id')->constrained('sistema_campos')->restrictOnDelete();
            $table->string('registro', 80);
            $table->json('valor');
            $table->string('busqueda', 190)->nullable();
            $table->timestamps();
            $table->unique(['campo_id', 'registro'], 'sistema_campo_registro_uq');
            $table->index(['campo_id', 'busqueda'], 'sistema_campo_busqueda_idx');
        });

        Schema::create('sistema_campo_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campo_id')->constrained('sistema_campos')->restrictOnDelete();
            $table->string('registro', 80);
            $table->json('valor');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['campo_id', 'registro'], 'sistema_campo_historial_idx');
        });

        Schema::create('sistema_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modulo', 40);
            $table->string('accion', 80);
            $table->string('registro', 100)->nullable();
            $table->string('resultado', 20)->default('aplicado');
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->uuid('solicitud')->nullable();
            $table->timestamp('created_at');
            $table->index(['modulo', 'accion', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Revertir el código conservando las tablas de configuración y la bitácora.');
    }
};
