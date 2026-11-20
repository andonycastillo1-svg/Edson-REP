<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('asignaciones_inventarios', 'sustituida_por_id')) {
            Schema::table('asignaciones_inventarios', function (Blueprint $table) {
                $table->foreignId('sustituida_por_id')->nullable()
                    ->constrained('asignaciones_inventarios', indexName: 'asig_sustituida_por_fk')->restrictOnDelete();
            });
        }

        Schema::create('regiones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
        Schema::table('colaboradores', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained('regiones')->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('coordinador_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('inspeccion_secciones', function (Blueprint $table) {
            $table->string('clave', 30)->primary();
            $table->string('nombre', 160);
            $table->unsignedSmallInteger('orden');
        });
        Schema::create('inspeccion_articulos', function (Blueprint $table) {
            $table->id();
            $table->string('seccion', 30);
            $table->foreign('seccion')->references('clave')->on('inspeccion_secciones')->restrictOnDelete();
            $table->string('producto_codigo')->nullable()->unique();
            $table->foreign('producto_codigo')->references('codigo')->on('productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('etiqueta', 160);
            $table->string('descripcion', 500)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unsignedSmallInteger('maximo');
            $table->boolean('activo')->default(true);
        });
        Schema::create('inspecciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('coordinador_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('region_id')->constrained('regiones')->restrictOnDelete();
            $table->string('colaborador_codigo');
            $table->foreign('colaborador_codigo')->references('codigo')->on('colaboradores')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('puesto');
            $table->date('fecha')->index();
            $table->uuid('solicitud_id');
            $table->unique(['supervisor_id', 'solicitud_id'], 'inspeccion_solicitud_unica');
            $table->timestamps();
        });
        Schema::create('inspeccion_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->constrained('inspecciones')->restrictOnDelete();
            $table->string('producto_codigo');
            $table->foreign('producto_codigo')->references('codigo')->on('productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('etiqueta', 160);
            $table->unsignedSmallInteger('cantidad');
            $table->unique(['inspeccion_id', 'producto_codigo'], 'inspeccion_producto_unico');
        });
        Schema::create('inspeccion_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->constrained('inspecciones')->restrictOnDelete();
            $table->string('ruta');
            $table->string('nombre');
        });
        Schema::create('consolidados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinador_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('operador_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->string('nombre', 160);
            $table->date('desde');
            $table->date('hasta');
            $table->string('estado', 25)->index();
            $table->timestamps();
        });
        Schema::create('consolidado_inspeccion', function (Blueprint $table) {
            $table->foreignId('consolidado_id')->constrained('consolidados')->restrictOnDelete();
            $table->foreignId('inspeccion_id')->unique()->constrained('inspecciones')->restrictOnDelete();
        });
        Schema::create('consolidado_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidado_id')->constrained('consolidados')->restrictOnDelete();
            $table->string('producto_codigo');
            $table->foreign('producto_codigo')->references('codigo')->on('productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('original');
            $table->unsignedInteger('cantidad');
            $table->unique(['consolidado_id', 'producto_codigo'], 'consolidado_producto_unico');
        });
        Schema::create('consolidado_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidado_id')->constrained('consolidados')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('accion', 30);
            $table->text('nota')->nullable();
            $table->json('cambios');
            $table->timestamps();
        });
        Schema::create('constancias_gerencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('medio', 15);
            $table->date('fecha');
            $table->string('autorizo', 160);
            $table->text('referencia');
            $table->json('plan');
            $table->string('ruta')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->timestamps();
        });
        Schema::create('consolidado_constancia', function (Blueprint $table) {
            $table->foreignId('consolidado_id')->constrained('consolidados')->restrictOnDelete();
            $table->foreignId('constancia_id')->constrained('constancias_gerencia')->restrictOnDelete();
            $table->primary(['consolidado_id', 'constancia_id'], 'consolidado_constancia_pk');
        });
        Schema::create('compra_consolidado', function (Blueprint $table) {
            $table->unsignedBigInteger('compra_id');
            $table->foreign('compra_id')->references('id')->on('compras')->restrictOnDelete();
            $table->foreignId('consolidado_id')->constrained('consolidados')->restrictOnDelete();
            $table->primary(['compra_id', 'consolidado_id']);
        });

        Schema::create('motivos_asignacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 45)->unique();
            $table->string('nombre', 120);
            $table->string('politica', 25);
            $table->boolean('activo')->default(true);
        });
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->foreignId('motivo_asignacion_id')->nullable()->constrained('motivos_asignacion')->restrictOnDelete();
            $table->string('motivo_registrado', 120)->nullable();
        });
        Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->dateTime('fecha_asignacion_anterior')->nullable()->change();
            $table->decimal('referencia_proporcional', 12, 2)->nullable();
        });
        Schema::create('importaciones_asignacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('bodegas')->restrictOnDelete();
            $table->json('filas');
            $table->json('entregas')->nullable();
            $table->char('huella_confirmada', 64)->nullable()->unique();
            $table->timestamp('confirmada_en')->nullable();
            $table->timestamps();
        });

        $this->sembrarCatalogos();
    }

    private function sembrarCatalogos(): void
    {
        foreach ([
            'comun' => 'EPP y herramienta común',
            'titular' => 'Equipo y herramienta de titular',
            'rescate' => 'Equipo de rescate en alturas titular',
            'supervision' => 'Equipo de supervisión',
            'motociclista' => 'Equipo de protección para técnico motociclista',
            'reflectivos' => 'Conos y reflectivos',
        ] as $clave => $nombre) {
            DB::table('inspeccion_secciones')->insert(['clave' => $clave, 'nombre' => $nombre,
                'orden' => DB::table('inspeccion_secciones')->count() + 1]);
        }
        foreach (['Cono', 'Reflectivos grandes para cono', 'Reflectivos pequeños para cono', 'Cinta reflectiva 3M (grado diamante)'] as $i => $nombre) {
            $codigos = DB::table('productos')->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->pluck('codigo');
            DB::table('inspeccion_articulos')->insert([
                'seccion' => 'reflectivos', 'etiqueta' => $nombre, 'maximo' => 7, 'orden' => $i + 1,
                'producto_codigo' => $codigos->count() === 1 ? $codigos->first() : null, 'activo' => $codigos->count() === 1,
            ]);
        }
        DB::table('motivos_asignacion')->insert([
            ['codigo' => 'desgaste', 'nombre' => 'Cambio por desgaste', 'politica' => 'sin_propuesta'],
            ['codigo' => 'robo', 'nombre' => 'Robo', 'politica' => 'revision'],
            ['codigo' => 'robo_injustificado', 'nombre' => 'Robo injustificado', 'politica' => 'proporcional'],
        ]);

        $rol = DB::table('roles')->where('nombre', 'Coordinador')->exists() ? 'Coordinador de requerimientos' : 'Coordinador';
        $id = max(5, (int) DB::table('roles')->max('id') + 1);
        DB::table('roles')->insert(['id' => $id, 'nombre' => $rol, 'ambito_operativo' => 5, 'activo' => true,
            'descripcion' => 'Revisión de requerimientos y consolidados del equipo', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['panel.consultar', 'flujo.inspecciones', 'flujo.consolidados', 'flujo.revisar'] as $permiso) {
            DB::table('sistema_permisos_rol')->insert(['role_id' => $id, 'permiso' => $permiso, 'permitido' => true]);
        }
    }

    public function down(): void
    {
        throw new LogicException('Los registros de inspecciones, consolidados y asignaciones deben conservarse.');
    }
};
