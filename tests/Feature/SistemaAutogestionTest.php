<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MovimientoInventarioNotification;
use App\Notifications\RelacionUsuarioNotification;
use App\Services\NotificacionService;
use App\Sistema\Ajustes;
use App\Sistema\Campo;
use App\Sistema\CatalogoPermisos;
use App\Sistema\Permisos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class SistemaAutogestionTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private User $admin;
    private User $operador;
    private Bodega $regional;
    private Bodega $principal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (CatalogoPermisos::AMBITOS as $id => $nombre) {
            DB::table('roles')->insert(['id' => $id, 'nombre' => $nombre]);
        }
        $this->regional = Bodega::create(['nombre' => 'Sucursal', 'tipo' => 'Regional']);
        $this->principal = Bodega::create(['nombre' => 'Central', 'tipo' => 'Principal']);
        $this->super = User::factory()->create(['role_id' => 1]);
        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->operador = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->regional->id]);
    }

    public function test_catalogo_preserva_las_rutas_legacy_y_deniega_las_desconocidas(): void
    {
        $permisos = app(Permisos::class);
        $supervisor = User::factory()->create(['role_id' => 3]);
        $rrhh = User::factory()->create(['role_id' => 4]);
        $identidades = [1 => $this->admin, 2 => $this->operador, 3 => $supervisor, 4 => $rrhh];
        foreach (Route::getRoutes() as $ruta) {
            foreach ($ruta->middleware() as $middleware) {
                if (str_starts_with($middleware, 'role:')) {
                    $rol = (int) substr($middleware, 5);
                    foreach ($identidades as $ambito => $user) {
                        $this->assertSame($rol === $ambito, $permisos->ruta($user, $ruta->getName()), $ruta->getName());
                    }
                }
            }
        }
        $this->assertFalse($permisos->permite($this->admin, 'inventario.permiso_desconocido'));
        $this->actingAs($this->operador)->get(route('operador.bodegas.show', $this->regional))->assertOk();
        $this->get(route('operador.bodegas.show', $this->principal))->assertForbidden();
        $this->get(route('operador.compras.index'))->assertForbidden();
    }

    public function test_promocion_explicita_y_proteccion_del_ultimo_superadministrador(): void
    {
        $this->actingAs($this->admin)->get(route('sistema.configuracion'))->assertForbidden();
        $this->putJson(route('sistema.usuario.superadministrador', $this->admin), [
            'activo' => true, 'current_password' => 'password',
        ])->assertForbidden();

        $this->artisan('sistema:superadministrador', ['usuario' => $this->super->id])
            ->expectsConfirmation('¿Confirmas la promoción explícita de esta cuenta?', 'yes')
            ->expectsQuestion('Contraseña actual de la cuenta', 'password')->assertSuccessful();

        $this->actingAs($this->super)->get(route('sistema.configuracion'))->assertOk()->assertSee('Roles y permisos');
        $this->get(route('sistema.seguridad'))->assertOk();
        $this->putJson(route('sistema.usuario.superadministrador', $this->super), [
            'activo' => false, 'current_password' => 'password',
        ])->assertUnprocessable();
        $this->putJson(route('sistema.usuario.estado', $this->super), [
            'activo' => false, 'current_password' => 'password',
        ])->assertUnprocessable();
        $this->putJson(route('sistema.rol.actualizar', 1), [
            'nombre' => 'Administrador', 'activo' => false, 'current_password' => 'password',
        ])->assertUnprocessable();
        $this->actingAs($this->admin)->delete(route('admin.usuarios.destroy', $this->super))->assertRedirect();
        $this->assertTrue($this->super->fresh()->acceso_activo);
        $this->assertDatabaseHas('sistema_auditoria', ['actor_id' => $this->super->id, 'accion' => 'superadministrador']);
        $this->assertDatabaseMissing('sistema_superadministradores', ['user_id' => $this->admin->id]);
    }

    public function test_duplicar_rol_y_precedencia_de_permisos_individuales_restringidos_por_ambito(): void
    {
        $this->superadministrador();
        $this->post(route('sistema.rol.crear'), [
            'nombre' => 'Operador de pruebas', 'ambito_operativo' => 2, 'copiar_rol' => 2, 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $rol = Role::where('nombre', 'Operador de pruebas')->firstOrFail();
        $cuenta = User::factory()->create(['role_id' => $rol->id, 'bodega_id' => $this->regional->id]);
        $this->get(route('sistema.rol', $rol))->assertOk()->assertSee('Acceso efectivo');
        $this->actingAs($cuenta)->get(route('operador.bodegas.index'))->assertOk();
        $this->get(route('admin.bodegas.index'))->assertForbidden();
        $this->get(route('sistema.datos.ficha', ['bodegas', $this->principal->id]))->assertNotFound();

        $this->actingAs($this->super)->putJson(route('sistema.usuario.permisos', $cuenta), [
            'reglas' => ['bodegas.consultar' => 'denegar', 'sistema.parametros' => 'permitir'], 'current_password' => 'incorrecta',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertDatabaseHas('sistema_auditoria', ['accion' => 'confirmacion_invalida', 'resultado' => 'denegado']);
        $this->put(route('sistema.usuario.permisos', $cuenta), [
            'reglas' => ['bodegas.consultar' => 'denegar', 'sistema.parametros' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->actingAs($cuenta->fresh())->get(route('operador.bodegas.index'))->assertForbidden();
        $this->get(route('sistema.parametros'))->assertOk();
        $this->get(route('sistema.seguridad'))->assertForbidden();
        $this->putJson(route('sistema.parametros.guardar'), ['inactividad' => 7, 'current_password' => 'password'])->assertForbidden();
        $this->put(route('sistema.parametros.guardar'), ['empresa' => 'Compañía de prueba'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sistema_auditoria', ['accion' => 'permiso_usuario', 'registro' => (string) $cuenta->id]);
    }

    public function test_bajas_conservan_cuentas_y_protegen_cuentas_delegadas(): void
    {
        $operativo = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->regional->id]);
        $this->actingAs($this->admin)->delete(route('admin.usuarios.destroy', $operativo))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $operativo->id, 'acceso_activo' => false]);
        $this->actingAs($operativo->fresh())->get(route('operador.dashboard'))->assertForbidden();

        $this->superadministrador();
        $this->put(route('sistema.usuario.permisos', $operativo), [
            'reglas' => ['sistema.parametros' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('admin.usuarios.edit', $operativo))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $operativo->id, 'acceso_activo' => false]);
    }

    public function test_superadministrador_puede_asignar_un_rol_delegado_y_no_puede_degradar_su_ultimo_acceso(): void
    {
        $this->superadministrador();
        $rol = Role::create(['nombre' => 'Operador delegado', 'ambito_operativo' => 2]);
        DB::table('sistema_permisos_rol')->insert(['role_id' => $rol->id, 'permiso' => 'sistema.parametros', 'permitido' => true]);
        $payload = [
            'role_id' => $rol->id, 'name' => $this->operador->name, 'email' => $this->operador->email,
            'bodega_id' => $this->regional->id, 'current_password' => 'password',
        ];
        $this->actingAs($this->admin)->putJson(route('sistema.usuario.acceso', $this->operador), $payload)->assertForbidden();
        $this->actingAs($this->super)->put(route('sistema.usuario.acceso', $this->operador), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $this->operador->id, 'role_id' => $rol->id, 'bodega_id' => $this->regional->id]);
        $this->putJson(route('sistema.usuario.acceso', $this->super), array_replace($payload, [
            'name' => $this->super->name, 'email' => $this->super->email,
        ]))->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->actingAs($this->operador->fresh())->get(route('sistema.parametros'))->assertOk();
    }

    public function test_campos_obligatorios_validan_altas_y_conservan_valores_al_editar_y_archivar(): void
    {
        $this->superadministrador();
        $definicion = $this->definicion(['obligatorio' => true, 'tipo' => 'entero', 'predeterminado' => null]);
        $this->post(route('sistema.campos.guardar'), $definicion)->assertSessionHasNoErrors();
        $campo = Campo::sole();
        $this->get(route('admin.bodegas.create'))->assertOk()->assertSee('Referencia de bodega');
        $data = ['nombre' => 'Pruebas', 'tipo' => 'Regional'];
        $this->postJson(route('admin.bodegas.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('campos_personalizados.referencia');
        $this->postJson(route('admin.bodegas.store'), $data + ['campos_personalizados' => ['referencia' => 'xyz']])->assertUnprocessable();
        $this->put(route('admin.bodegas.update', $this->regional), ['nombre' => 'Sucursal anterior', 'tipo' => 'Regional'])->assertSessionHasNoErrors();

        $this->post(route('admin.bodegas.store'), $data + ['campos_personalizados' => ['referencia' => 17]])->assertSessionHasNoErrors();
        $id = Bodega::where('nombre', 'Pruebas')->value('id');
        $this->get(route('sistema.datos.ficha', ['bodegas', $id]))->assertOk()->assertSee('17');
        $this->put(route('sistema.datos.actualizar', ['bodegas', $id]), ['campos_personalizados' => ['referencia' => 24]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sistema_campo_historial', ['campo_id' => $campo->id, 'registro' => (string) $id, 'valor' => '17']);
        $this->putJson(route('sistema.campos.actualizar', $campo), array_replace($definicion, ['tipo' => 'texto']))->assertUnprocessable();
        $this->patch(route('sistema.campos.archivar', $campo))->assertRedirect();
        $this->assertNotNull($campo->fresh()->archivado_en);
        $this->assertDatabaseHas('sistema_campo_valores', ['campo_id' => $campo->id, 'registro' => (string) $id, 'valor' => '24']);
    }

    public function test_filtros_exportacion_y_permisos_de_campo_no_amplian_el_ambito(): void
    {
        $this->superadministrador();
        $this->post(route('sistema.campos.guardar'), $this->definicion())->assertSessionHasNoErrors();
        $campo = Campo::sole();
        $this->put(route('sistema.datos.actualizar', ['bodegas', $this->principal]), ['campos_personalizados' => ['referencia' => 'Central-Privado']])->assertSessionHasNoErrors();
        $this->put(route('sistema.datos.actualizar', ['bodegas', $this->regional]), ['campos_personalizados' => ['referencia' => 'Sucursal-Visible']])->assertSessionHasNoErrors();

        $this->actingAs($this->operador)->get(route('sistema.datos.lista', ['modulo' => 'bodegas', 'campo' => [$campo->id => 'Sucursal-Visible']]))
            ->assertOk()->assertSee('Sucursal-Visible')->assertDontSee('Central-Privado');
        $this->get(route('sistema.datos.exportar', 'bodegas'))->assertForbidden();
        $this->actingAs($this->super)->put(route('sistema.usuario.permisos', $this->operador), [
            'reglas' => ['bodegas.datos_exportar' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $csv = $this->actingAs($this->operador->fresh())->get(route('sistema.datos.exportar', 'bodegas'))->assertDownload('bodegas.csv')->streamedContent();
        $this->assertStringContainsString('Sucursal-Visible', $csv);
        $this->assertStringNotContainsString('Central-Privado', $csv);

        $this->actingAs($this->super)->put(route('sistema.campos.actualizar', $campo), $this->definicion([
            'permiso_lectura' => 'sistema.parametros', 'current_password' => 'password',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($this->operador->fresh())->get(route('sistema.datos.lista', 'bodegas'))->assertOk()->assertDontSee('Sucursal-Visible');
        $this->getJson(route('sistema.datos.lista', ['modulo' => 'bodegas', 'campo' => [$campo->id => 'Sucursal-Visible']]))
            ->assertUnprocessable();
    }

    public function test_selectores_predeterminados_vinculan_filas_nuevas_y_preservan_la_configuracion_ante_un_error(): void
    {
        $this->superadministrador();
        $datos = $this->definicion([
            'tipo' => 'multiple', 'obligatorio' => true, 'seleccion_predeterminada' => [2, 5],
            'opciones' => [
                2 => ['etiqueta' => 'Operación', 'activo' => true],
                5 => ['etiqueta' => 'Mantenimiento', 'activo' => true],
                8 => ['etiqueta' => 'Archivada', 'activo' => false],
            ],
        ]);
        $this->post(route('sistema.campos.guardar'), $datos)->assertSessionHasNoErrors();
        $campo = Campo::sole();
        $ids = $campo->opciones->where('activo', true)->pluck('id')->all();
        $this->assertSame($ids, $campo->predeterminado);
        $this->get(route('sistema.campos.editar', $campo))->assertOk()->assertSee('Operación')->assertSee('Mantenimiento');
        $this->post(route('admin.bodegas.store'), ['nombre' => 'Bodega con valores predeterminados', 'tipo' => 'Regional'])->assertSessionHasNoErrors();
        $bodega = Bodega::where('nombre', 'Bodega con valores predeterminados')->firstOrFail();
        $this->assertDatabaseHas('sistema_campo_valores', ['campo_id' => $campo->id, 'registro' => (string) $bodega->id, 'valor' => json_encode($ids)]);

        $opciones = $campo->opciones->mapWithKeys(fn ($opcion) => [$opcion->id => $opcion->only('id', 'etiqueta', 'activo')])->all();
        $opciones[$ids[0]]['activo'] = false;
        $this->putJson(route('sistema.campos.actualizar', $campo), array_replace($datos, [
            'tabla' => false, 'opciones' => $opciones, 'seleccion_predeterminada' => $ids,
        ]))->assertUnprocessable()->assertJsonValidationErrors('seleccion_predeterminada');
        $this->assertSame($ids, $campo->fresh()->predeterminado);
        $this->assertTrue($campo->fresh()->tabla);
        $this->assertTrue($campo->opciones()->find($ids[0])->activo);

        $this->post(route('sistema.campos.guardar'), $this->definicion([
            'tipo' => 'lista', 'clave' => 'otra_referencia', 'seleccion_predeterminada' => [''],
            'opciones' => [['etiqueta' => 'Solo una', 'activo' => true]],
        ]))->assertSessionHasNoErrors();
        $this->assertNull(Campo::where('clave', 'otra_referencia')->sole()->predeterminado);
    }

    public function test_paneles_reflejan_las_reglas_efectivas_y_el_alcance_de_bodega(): void
    {
        $this->superadministrador();
        $this->put(route('sistema.usuario.permisos', $this->operador), [
            'reglas' => ['bodegas.consultar' => 'denegar', 'sistema.parametros' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->operador->fresh())->get(route('operador.dashboard'))->assertOk()
            ->assertDontSee(route('operador.bodegas.index'))
            ->assertDontSee(route('operador.compras.index'))
            ->assertSee(route('sistema.configuracion'));
        $this->get(route('operador.bodegas.index'))->assertForbidden()->assertSee('Acceso denegado');
        $this->get(route('sistema.presentacion'))->assertForbidden();
    }

    public function test_archivo_privado_requiere_permiso_y_registro_en_su_bodega(): void
    {
        Storage::fake('documentos');
        $this->superadministrador();
        $this->post(route('sistema.campos.guardar'), $this->definicion(['tipo' => 'archivo', 'filtro' => false]))->assertSessionHasNoErrors();
        $campo = Campo::sole();
        $formulario = ['campos_personalizados' => ['referencia' => UploadedFile::fake()->create('respaldo.pdf', 5, 'application/pdf')]];
        foreach ([$this->regional, $this->principal] as $bodega) {
            $this->put(route('sistema.datos.actualizar', ['bodegas', $bodega]), $formulario)->assertSessionHasNoErrors();
        }
        $ruta = fn ($bodega) => route('sistema.datos.archivo', ['bodegas', $bodega, $campo]);
        $this->actingAs($this->operador)->get($ruta($this->regional))->assertForbidden();
        $this->actingAs($this->super)->put(route('sistema.usuario.permisos', $this->operador), [
            'reglas' => ['bodegas.datos_archivos' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->operador->fresh())->get($ruta($this->regional))->assertDownload('respaldo.pdf');
        $this->get($ruta($this->principal))->assertNotFound();
        $this->assertCount(2, Storage::disk('documentos')->allFiles('campos-sistema'));
    }

    public function test_opcion_desactivada_conserva_valor_y_deja_de_admitirse_en_altas(): void
    {
        $this->superadministrador();
        $opciones = [
            ['etiqueta' => 'Primera', 'orden' => 1, 'activo' => true],
            ['etiqueta' => 'Segunda', 'orden' => 2, 'activo' => true],
        ];
        $this->post(route('sistema.campos.guardar'), $this->definicion(['tipo' => 'lista', 'opciones' => $opciones]))->assertSessionHasNoErrors();
        $campo = Campo::sole()->load('opciones');
        $primera = $campo->opciones->first();
        $this->put(route('sistema.datos.actualizar', ['bodegas', $this->regional]), ['campos_personalizados' => ['referencia' => $primera->id]])->assertSessionHasNoErrors();
        $this->put(route('sistema.campos.actualizar', $campo), $this->definicion([
            'tipo' => 'lista', 'opciones' => [['id' => $primera->id, 'etiqueta' => 'Primera histórica', 'activo' => false]],
        ]))->assertSessionHasNoErrors();
        $this->get(route('sistema.datos.ficha', ['bodegas', $this->regional]))->assertOk()->assertSee('Primera histórica (inactiva)');
        $this->postJson(route('admin.bodegas.store'), [
            'nombre' => 'Nueva', 'tipo' => 'Regional', 'campos_personalizados' => ['referencia' => $primera->id],
        ])->assertUnprocessable();
        $this->assertDatabaseHas('sistema_campo_valores', ['campo_id' => $campo->id, 'valor' => (string) $primera->id]);
    }

    public function test_presentacion_global_valida_nativos_y_restaura_valores(): void
    {
        $this->superadministrador();
        $this->get(route('sistema.presentacion'))->assertOk();
        $this->put(route('sistema.presentacion.guardar', 'bodegas'), [
            'columnas' => ['id' => ['visible' => false, 'orden' => 1, 'filtro' => false, 'buscar' => false],
                'nombre' => ['etiqueta' => 'Nombre visible', 'visible' => true, 'orden' => 3, 'filtro' => true, 'buscar' => true]],
            'formulario' => ['ubicacion' => ['etiqueta' => 'Dirección verificable', 'visible' => true, 'obligatorio' => true, 'orden' => 0]],
            'pagina' => 10, 'ordenar' => 'nombre', 'direccion' => 'asc',
        ])->assertSessionHasNoErrors();
        $this->get(route('admin.bodegas.create'))->assertOk()->assertSee('Dirección verificable');
        $this->postJson(route('admin.bodegas.store'), ['nombre' => 'Ubicación omitida', 'tipo' => 'Regional'])->assertUnprocessable();
        $this->actingAs($this->operador)->get(route('sistema.datos.lista', ['modulo' => 'bodegas', 'q' => 'Sucursal']))
            ->assertOk()->assertSee('Nombre visible')->assertDontSee('Central');
        $this->actingAs($this->super)->put(route('sistema.presentacion.guardar', 'bodegas'), ['restaurar' => true])->assertSessionHasNoErrors();
        $this->get(route('admin.bodegas.create'))->assertOk()->assertSee('Ubicación')->assertDontSee('Dirección verificable');
    }

    public function test_notificaciones_configurables_filtran_sin_ampliar_y_seguridad_sigue_activa(): void
    {
        Notification::fake();
        $this->superadministrador();
        $elegible = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->principal->id]);
        $this->get(route('sistema.notificaciones'))->assertOk();
        $this->put(route('sistema.notificaciones.guardar', 'movimiento_inventario'), [
            'activo' => true, 'usuarios' => [$elegible->id, $this->operador->id],
        ])->assertSessionHasNoErrors();
        $servicio = app(NotificacionService::class);
        $servicio->notificarMovimientoInventario($this->principal, $this->super, 'Prueba');
        Notification::assertSentToTimes($elegible, MovimientoInventarioNotification::class, 1);
        Notification::assertNotSentTo($this->operador, MovimientoInventarioNotification::class);
        Notification::assertNotSentTo($this->admin, MovimientoInventarioNotification::class);

        $this->putJson(route('sistema.notificaciones.guardar', 'relacion_usuario'), ['activo' => false])->assertNotFound();
        $supervisor = User::factory()->create(['role_id' => 3]);
        $servicio->notificarRelacionSupervisorAlmacenista($supervisor, $elegible, $this->super);
        Notification::assertSentToTimes($supervisor, RelacionUsuarioNotification::class, 1);
        $this->get(route('sistema.auditoria', ['modulo' => 'configuracion']))->assertOk()->assertSee('notificacion_movimiento_inventario');
        $this->assertArrayHasKey('movimiento_inventario', app(Ajustes::class)->leer('notificaciones'));
    }

    public function test_campos_se_integran_en_altas_operativas_y_saldos_automaticos(): void
    {
        Notification::fake();
        $this->superadministrador();
        foreach (['usuarios','colaboradores','productos','compras','inventario','asignaciones','traslados','vehiculos','refacciones'] as $modulo) {
            $this->post(route('sistema.campos.guardar'), $this->definicion([
                'modulo' => $modulo, 'predeterminado' => $modulo === 'inventario' ? 'Saldo automático' : null, 'obligatorio' => true,
            ]))->assertSessionHasNoErrors()->assertRedirect();
        }
        $campo = fn ($valor) => ['campos_personalizados' => ['referencia' => $valor]];
        $this->post(route('admin.usuarios.store'), $campo('Usuario de prueba') + [
            'name' => 'Nuevo operador', 'email' => 'nuevo.operador@example.test', 'password' => 'fixture-2026',
            'password_confirmation' => 'fixture-2026', 'role_id' => 2, 'bodega_id' => $this->regional->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.colaboradores.store'), $campo('Legajo') + [
            'codigo' => 'COL-T', 'nombre' => 'Persona', 'puesto' => 'Soporte', 'estado' => 'Activo',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->post(route('admin.compras.store'), $campo('Entrada contable') + [
            'fecha_compra' => today()->toDateString(), 'no_factura' => 'F-CONFIG-1', 'proveedor_tipo' => 'nuevo',
            'proveedor_nombre' => 'Pruebas', 'forma_pago' => 'Al contado', 'producto_tipo' => ['nuevo'],
            'producto_codigo' => [null], 'producto_codigo_nuevo' => ['ART-T'], 'producto_nombre' => ['Refacción'],
            'producto_categoria' => ['Refacciones'], 'cantidad' => [5], 'precio_unitario' => ['1.20'],
            'campos_producto' => [['referencia' => 'Ficha técnica']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.asignaciones.store'), $campo('Constancia') + [
            'colaborador_codigo' => 'COL-T', 'fecha' => today()->toDateString(),
            'aprobado_por' => 'Gerencia', 'medio_solicitud' => 'Correo',
            'items' => [['producto_codigo' => 'ART-T', 'bodega_id' => $this->principal->id, 'cantidad_asignada' => 1]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.operaciones.traslados.store'), $campo('Guía interna') + [
            'bodega_origen_id' => $this->principal->id, 'bodega_destino_id' => $this->regional->id,
            'lineas' => [['producto_codigo' => 'ART-T', 'cantidad' => 1]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.vehiculos.store'), $campo('Flota') + [
            'vin' => 'VIN-T', 'placa' => 'PLACA-T', 'estado' => 'Disponible',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.vehiculos.productos.store'), $campo('Bitácora del vehículo') + [
            'vehiculo_vin' => 'VIN-T', 'producto_codigo' => 'ART-T', 'bodega_id' => $this->principal->id,
            'cantidad' => 1, 'fecha' => today()->toDateString(), 'motivo' => 'Prueba',
        ])->assertSessionHasNoErrors()->assertRedirect();

        foreach ([
            'usuarios' => 'Usuario de prueba', 'colaboradores' => 'Legajo', 'productos' => 'Ficha técnica',
            'compras' => 'Entrada contable', 'inventario' => 'Saldo automático', 'asignaciones' => 'Constancia',
            'traslados' => 'Guía interna', 'vehiculos' => 'Flota', 'refacciones' => 'Bitácora del vehículo',
        ] as $modulo => $valor) {
            $this->get(route('sistema.datos.lista', $modulo))->assertOk()->assertSee($valor);
        }
    }

    public function test_parametros_limitan_bodega_principal_y_emitir_alerta_de_stock(): void
    {
        Notification::fake();
        $this->superadministrador();
        $this->put(route('sistema.parametros.guardar'), ['nombre' => 'Inventario Central', 'stock_minimo' => 2])->assertSessionHasNoErrors();
        $this->get(route('sistema.parametros'))->assertOk()->assertSee('Inventario Central');
        $producto = \App\Models\Producto::create(['codigo' => 'MIN-T', 'nombre' => 'Mínimo']);
        $stock = app(\App\Services\InventarioStockService::class);
        DB::transaction(fn () => $stock->incrementar($this->principal->id, $producto->codigo, 3));
        DB::transaction(fn () => $stock->trasladar($this->principal->id, $this->regional->id, $producto->codigo, 2));
        Notification::assertSentToTimes($this->admin, \App\Notifications\StockBajoNotification::class, 1);
        DB::transaction(fn () => $stock->trasladar($this->principal->id, $this->regional->id, $producto->codigo, 1));
        Notification::assertSentToTimes($this->admin, \App\Notifications\StockBajoNotification::class, 1);

        DB::table('movimientos')->insert([
            'producto_codigo' => $producto->codigo, 'bodega_destino_id' => $this->principal->id,
            'tipo_movimiento' => 'Entrada', 'cantidad' => 3, 'fecha' => now(), 'user_id' => $this->super->id,
        ]);
        $otra = Bodega::create(['nombre' => 'Otra principal', 'tipo' => 'Principal']);
        $this->putJson(route('sistema.parametros.guardar'), [
            'bodega_principal' => $otra->id, 'current_password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('bodega_principal');
    }

    private function superadministrador(): void
    {
        DB::table('sistema_superadministradores')->insert(['user_id' => $this->super->id, 'activo' => true]);
        $this->actingAs($this->super);
    }

    private function definicion(array $cambios = []): array
    {
        return array_replace([
            'modulo' => 'bodegas', 'clave' => 'referencia', 'etiqueta' => 'Referencia de bodega',
            'tipo' => 'texto', 'activo' => true, 'obligatorio' => false, 'formulario' => true,
            'tabla' => true, 'filtro' => true, 'exportacion' => true, 'orden' => 1,
        ], $cambios);
    }
}
