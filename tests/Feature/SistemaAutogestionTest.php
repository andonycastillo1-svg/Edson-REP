<?php

namespace Tests\Feature;

use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Producto;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\RefreshDatabase;
use Tests\Support\ConDevolucionesCompletas;
use Tests\TestCase;

class SistemaAutogestionTest extends TestCase
{
    use RefreshDatabase, ConDevolucionesCompletas;

    private User $super;
    private User $admin;
    private User $operador;
    private Bodega $regional;
    private Bodega $principal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (array_intersect_key(CatalogoPermisos::AMBITOS, array_flip([1, 2, 3, 4])) as $id => $nombre) {
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
        config(['modulos.vehiculos' => true]);
        $permisos = app(Permisos::class);
        $supervisor = User::factory()->create(['role_id' => 3]);
        $rrhh = User::factory()->create(['role_id' => 4]);
        $coordinador = User::factory()->create(['role_id' => Role::where('ambito_operativo', 5)->sole()->id]);
        $identidades = [1 => $this->admin, 2 => $this->operador, 3 => $supervisor, 4 => $rrhh, 5 => $coordinador];
        foreach (Route::getRoutes() as $ruta) {
            foreach ($ruta->middleware() as $middleware) {
                if (str_starts_with($middleware, 'role:')) {
                    $rol = (int) substr($middleware, 5);
                    foreach ($identidades as $ambito => $user) {
                        $permiso = \App\Sistema\CatalogoPermisos::permisoRutaInventario($ruta->getName());
                        $esperado = $permiso === null ? $rol === $ambito
                            : app(\App\Services\AccesoInventarioOperativo::class)->tiene($user, $permiso);
                        $this->assertSame($esperado, $permisos->ruta($user, $ruta->getName()), $ruta->getName());
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

    public function test_tarjetas_de_permiso_distinguen_herencia_excepcion_y_acceso_guardado(): void
    {
        $this->superadministrador();
        $vista = $this->get(route('sistema.rol', 1))->assertOk();
        $vista->assertSee('Registrar compras')->assertSee('Documentos y reportes')
            ->assertSee('name="reglas[compras.crear]" value="heredar" checked', false)
            ->assertSee('name="reglas[compras.crear]" value="permitir"', false)
            ->assertSee('name="reglas[compras.crear]" value="denegar"', false);

        $cuenta = route('sistema.usuario', $this->operador);
        $accion = route('sistema.usuario.permisos', $this->operador);
        $this->from($cuenta)->put($accion, [
            'reglas' => ['bodegas.consultar' => 'denegar'], 'current_password' => 'incorrecta',
        ])->assertSessionHasErrors('current_password');
        $this->get($cuenta)->assertOk()->assertSee('Del rol')->assertSee('Acceso efectivo')
            ->assertSee('name="reglas[bodegas.consultar]" value="denegar" checked', false);
        $this->assertDatabaseMissing('sistema_permisos_usuario', ['user_id' => $this->operador->id, 'permiso' => 'bodegas.consultar']);

        $this->put($accion, ['reglas' => ['bodegas.consultar' => 'denegar'], 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->get($cuenta)->assertOk()->assertSee('Regla individual');
        $this->put($accion, ['reglas' => ['bodegas.consultar' => 'heredar'], 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sistema_permisos_usuario', ['user_id' => $this->operador->id, 'permiso' => 'bodegas.consultar']);
        $this->actingAs($this->operador->fresh())->get(route('operador.bodegas.index'))->assertOk();
    }

    public function test_contrasena_desde_seguridad_exige_superadministrador_y_preserva_el_acceso(): void
    {
        $ruta = route('sistema.usuario.contrasena', $this->operador);
        $nueva = 'Prueba-De-Cuenta-48!';
        $datos = ['password' => $nueva, 'password_confirmation' => $nueva, 'current_password' => 'password'];
        $anterior = $this->operador->fresh();
        $this->actingAs($this->admin)->putJson($ruta, $datos)->assertForbidden();
        $this->superadministrador();
        $this->putJson($ruta, array_replace($datos, ['current_password' => 'incorrecta']))
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson($ruta, array_replace($datos, ['password_confirmation' => 'diferente']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($anterior->password, $this->operador->fresh()->password);

        $this->put($ruta, $datos)->assertRedirect(route('sistema.usuario', $this->operador).'#seguridad')->assertSessionHasNoErrors();
        $cuenta = $this->operador->fresh();
        $this->assertTrue(Hash::check($nueva, $cuenta->password));
        $this->assertSame($this->operador->role_id, $cuenta->role_id);
        $this->assertSame($this->operador->bodega_id, $cuenta->bodega_id);
        $this->assertSame($anterior->acceso_activo, $cuenta->acceso_activo);
        $this->assertNull($cuenta->remember_token);
        $this->assertDatabaseHas('sistema_auditoria', ['registro' => (string) $cuenta->id, 'accion' => 'actualizar_contrasena', 'despues' => '{"credencial_actualizada":true}']);
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
        $this->put(route('sistema.operacion.campos.actualizar', ['bodegas', $id]), ['campos_personalizados' => ['referencia' => 24]])->assertSessionHasNoErrors();
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
        $this->put(route('sistema.operacion.campos.actualizar', ['bodegas', $this->principal]), ['campos_personalizados' => ['referencia' => 'Central-Privado']])->assertSessionHasNoErrors();
        $this->put(route('sistema.operacion.campos.actualizar', ['bodegas', $this->regional]), ['campos_personalizados' => ['referencia' => 'Sucursal-Visible']])->assertSessionHasNoErrors();

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
        $this->get(route('operador.bodegas.index'))->assertOk()->assertDontSee('Referencia de bodega')->assertDontSee('Sucursal-Visible');
        $this->getJson(route('operador.bodegas.index', ['campo' => [$campo->id => 'Sucursal-Visible']]))->assertUnprocessable();
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
            $this->put(route('sistema.operacion.campos.actualizar', ['bodegas', $bodega]), $formulario)->assertSessionHasNoErrors();
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
        $this->put(route('sistema.operacion.campos.actualizar', ['bodegas', $this->regional]), ['campos_personalizados' => ['referencia' => $primera->id]])->assertSessionHasNoErrors();
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

    public function test_consulta_de_solo_lectura_conserva_edicion_autorizada_en_operaciones(): void
    {
        $this->superadministrador();
        $this->post(route('sistema.campos.guardar'), $this->definicion([
            'clave' => '', 'etiqueta' => 'Ubicación de revisión',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('ubicacion_de_revision', Campo::sole()->clave);
        $registro = ['bodegas', $this->regional];
        $ficha = route('sistema.datos.ficha', $registro);
        $editor = route('sistema.operacion.campos.editar', $registro);
        $cambio = ['campos_personalizados' => ['ubicacion_de_revision' => 'Estante norte']];

        $this->get($ficha)->assertOk()->assertSee('Información del registro')
            ->assertDontSee('Guardar campos')->assertDontSee($editor);
        $this->putJson($ficha, $cambio)->assertMethodNotAllowed();
        $this->assertDatabaseCount('sistema_campo_valores', 0);
        $this->get($editor)->assertOk()->assertSee('Guardar campos');
        $this->put(route('sistema.operacion.campos.actualizar', $registro), $cambio)->assertSessionHasNoErrors();
        $this->get($ficha)->assertOk()->assertSee('Estante norte')->assertDontSee('Guardar campos');

        $this->actingAs($this->operador)->get($ficha)->assertOk()->assertSee('Estante norte');
        $this->get($editor)->assertForbidden();
        $this->get(route('sistema.presentacion'))->assertForbidden();
    }

    public function test_consulta_y_csv_respetan_filtros_columnas_y_orden_sin_guardar_preferencias_individuales(): void
    {
        $this->superadministrador();
        Bodega::create(['nombre' => 'Zona Alfa', 'tipo' => 'Regional', 'ubicacion' => 'Dato reservado para tabla']);
        Bodega::create(['nombre' => 'Zona Zeta', 'tipo' => 'Regional']);
        $this->get(route('sistema.datos.lista', ['modulo' => 'bodegas', 'q' => 'Zona']))->assertOk()
            ->assertSee('Zona Alfa')->assertDontSee('Central');

        $this->put(route('sistema.presentacion.guardar', 'bodegas'), [
            'pagina' => 10, 'ordenar' => 'nombre', 'direccion' => 'asc',
            'columnas' => [
                'nombre' => ['etiqueta' => 'Nombre público', 'orden' => 0, 'visible' => false, 'buscar' => true, 'filtro' => false],
                'tipo' => ['orden' => 1, 'visible' => true, 'buscar' => false, 'filtro' => true],
                'ubicacion' => ['orden' => 2, 'visible' => false, 'buscar' => true, 'filtro' => true],
            ],
        ])->assertSessionHasNoErrors();
        $this->put(route('sistema.usuario.permisos', $this->super), [
            'reglas' => ['bodegas.datos_exportar' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->super->fresh());

        $criterios = ['modulo' => 'bodegas', 'q' => 'Zona', 'nativo' => ['tipo' => 'Regional']];
        $this->get(route('sistema.datos.lista', $criterios))->assertOk()
            ->assertSeeInOrder(['Zona Alfa', 'Zona Zeta'])->assertSee('Nombre público')
            ->assertSee(route('sistema.datos.lista', $criterios + ['orden' => 'nombre', 'direccion' => 'desc']))
            ->assertDontSee('Dato reservado para tabla');
        $this->get(route('sistema.datos.lista', $criterios + ['orden' => 'nombre', 'direccion' => 'desc']))->assertOk()
            ->assertSeeInOrder(['Zona Zeta', 'Zona Alfa']);
        $csv = $this->get(route('sistema.datos.exportar', $criterios + ['orden' => 'nombre', 'direccion' => 'desc']))
            ->assertDownload('bodegas.csv')->streamedContent();
        $this->assertStringContainsString('Nombre público', $csv);
        $this->assertStringNotContainsString('Dato reservado para tabla', $csv);
        $this->assertStringNotContainsString('Central', $csv);
        $this->assertLessThan(strpos($csv, 'Zona Alfa'), strpos($csv, 'Zona Zeta'));
        $this->getJson(route('sistema.datos.lista', ['modulo' => 'bodegas', 'orden' => 'ubicacion']))->assertUnprocessable();
        $this->getJson(route('sistema.datos.lista', ['modulo' => 'bodegas', 'nativo' => ['ubicacion' => 'Dato']]))->assertUnprocessable();
        $this->get(route('sistema.datos.lista', $criterios))->assertOk()->assertSeeInOrder(['Zona Alfa', 'Zona Zeta']);
    }

    public function test_bitacora_ofrece_solo_filtros_del_alcance_y_resume_los_cambios(): void
    {
        $this->superadministrador();
        $this->put(route('sistema.usuario.permisos', $this->operador), [
            'reglas' => ['sistema.auditoria' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $bitacora = app(\App\Sistema\Auditoria::class);
        $bitacora->registrar('configuracion', 'parametros', 'general', ['empresa' => 'Anterior'], [
            'empresa' => 'Empresa de auditoría', 'ruta_interna' => 'ruta-privada-sintetica',
        ]);
        $fecha = now()->toDateString();
        $this->get(route('sistema.auditoria', ['actor' => $this->super->id, 'accion' => 'parametros', 'desde' => $fecha, 'hasta' => $fecha]))
            ->assertOk()->assertSee('Cambiar parámetros generales')->assertSee('Empresa de auditoría')
            ->assertSee('Anterior')->assertDontSee('ruta-privada-sintetica');
        $this->getJson(route('sistema.auditoria', ['desde' => $fecha, 'hasta' => now()->subDay()->toDateString()]))->assertUnprocessable();
        $this->get(route('sistema.auditoria', ['q' => 'CONFIGURACION parámetros']))->assertOk()->assertSee('Empresa de auditoría');
        $this->get(route('sistema.auditoria', ['q' => 'ruta-privada-sintetica']))->assertOk()->assertSee('No hay eventos para estos filtros.');

        $this->actingAs($this->operador->fresh());
        $bitacora->registrar('bodegas', 'guardar_campo', $this->regional->id);
        $this->get(route('sistema.auditoria'))->assertOk()->assertSee('Guardar valor de un campo')
            ->assertDontSee('Empresa de auditoría')->assertDontSee('Cambiar acceso de una cuenta');
        $this->get(route('sistema.auditoria', ['q' => 'configuración']))->assertOk()->assertSee('No hay eventos para estos filtros.');
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
        config(['modulos.vehiculos' => true]);
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
            'solicitud_id' => (string) \Illuminate\Support\Str::uuid(),
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

    public function test_usuarios_permite_asignar_roles_personalizados_con_las_protecciones_de_su_ambito(): void
    {
        $this->superadministrador();
        foreach (['Auditor interno' => 1, 'Almacenista auxiliar' => 2] as $nombre => $ambito) {
            $this->post(route('sistema.rol.crear'), [
                'nombre' => $nombre, 'ambito_operativo' => $ambito, 'copiar_rol' => $ambito, 'current_password' => 'password',
            ])->assertSessionHasNoErrors();
        }
        $auditor = Role::where('nombre', 'Auditor interno')->sole();
        $auxiliar = Role::where('nombre', 'Almacenista auxiliar')->sole();
        $datos = [
            'name' => 'Cuenta de auditoría', 'email' => 'auditor@example.test', 'role_id' => $auditor->id,
            'password' => 'Cuenta-Auditoria-480!', 'password_confirmation' => 'Cuenta-Auditoria-480!',
        ];

        $this->get(route('admin.usuarios.create'))->assertOk()->assertSee('Auditor interno')->assertSee('Almacenista auxiliar');
        $this->postJson(route('admin.usuarios.store'), $datos + ['current_password' => 'incorrecta'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->actingAs($this->admin)->get(route('admin.usuarios.create'))->assertOk()
            ->assertDontSee('Auditor interno')->assertSee('Almacenista auxiliar');
        $this->postJson(route('admin.usuarios.store'), $datos + ['current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->assertDatabaseMissing('users', ['email' => $datos['email']]);

        $this->actingAs($this->super)->post(route('admin.usuarios.store'), $datos + ['current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $cuenta = User::where('email', $datos['email'])->sole();
        $this->assertSame($auditor->id, $cuenta->role_id);
        $this->assertDatabaseMissing('sistema_superadministradores', ['user_id' => $cuenta->id]);
        $this->get(route('admin.usuarios.edit', $cuenta))->assertOk()->assertSee('Auditor interno');
        $this->get(route('admin.usuarios.edit', $this->super))->assertRedirect(route('admin.usuarios.index'));

        $edicion = ['name' => $datos['name'], 'email' => $datos['email'], 'role_id' => $auxiliar->id, 'current_password' => 'password'];
        $this->putJson(route('admin.usuarios.update', $cuenta), $edicion)->assertUnprocessable()->assertJsonValidationErrors('bodega_id');
        $this->put(route('admin.usuarios.update', $cuenta), $edicion + ['bodega_id' => $this->regional->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $cuenta->id, 'role_id' => $auxiliar->id, 'bodega_id' => $this->regional->id]);
    }

    public function test_acciones_de_devolucion_siguen_la_regla_efectiva_y_el_alcance_de_bodega(): void
    {
        $this->superadministrador();
        $colaborador = Colaborador::create(['codigo' => 'C-QA', 'nombre' => 'Persona de pruebas', 'puesto' => 'Técnico', 'estado' => 'Activo']);
        $producto = Producto::create(['codigo' => 'P-QA', 'nombre' => 'Equipo de pruebas', 'categoria' => 'Equipo']);
        $asignacion = AsignacionInventario::create([
            'user_id' => $this->operador->id, 'bodega_id' => $this->regional->id, 'producto_codigo' => $producto->codigo,
            'colaborador_codigo' => $colaborador->codigo, 'cantidad_asignada' => 2, 'costo_unitario' => 12, 'estado' => 'Activa', 'fecha' => now(),
        ]);
        $listado = route('operador.asignaciones.index');
        $detalle = route('operador.asignaciones.colaborador', $colaborador->codigo);
        $formulario = route('devoluciones.formulario', $colaborador->codigo);
        $devolucion = route('devoluciones.registrar', $colaborador->codigo);
        $rutaExcepciones = route('sistema.usuario.permisos', $this->operador);

        foreach (['heredar', 'denegar', 'permitir'] as $regla) {
            $this->actingAs($this->super)->put($rutaExcepciones, [
                'reglas' => ['asignaciones.devolver' => $regla], 'current_password' => 'password',
            ])->assertSessionHasNoErrors();
            $this->actingAs($this->operador->fresh());
            $this->get($listado)->assertOk()->assertSee('Nueva asignación')->assertSee('Historial de movimientos')
                ->assertDontSee('name="seleccionadas[]"', false);
            $vista = $this->get($detalle)->assertOk();
            if ($regla === 'denegar') {
                $vista->assertDontSee('Registrar devolución');
                $this->get($formulario)->assertForbidden();
                $this->postJson($devolucion, ['seleccionadas' => [$asignacion->id], 'devoluciones' => [$asignacion->id => 1]])
                    ->assertForbidden();
            } else {
                $vista->assertSee('Registrar devolución');
                $this->get($formulario)->assertOk()->assertSee('Artículos bajo custodia');
            }
            $this->get(route('operador.asignaciones.create'))->assertOk();
        }

        $asignacion->replicate()->fill(['bodega_id' => $this->principal->id])->save();
        $this->get($formulario)->assertForbidden();
        $this->postJson($devolucion, $this->datosDevolucion($asignacion))->assertForbidden();
        $this->assertSame(2, $asignacion->fresh()->cantidad_asignada);
    }

    public function test_departamento_se_persiste_filtra_ordena_y_configura_en_ambas_superficies(): void
    {
        $this->superadministrador();
        $this->post(route('sistema.campos.guardar'), $this->definicion([
            'modulo' => 'colaboradores', 'clave' => 'departamento', 'etiqueta' => 'Departamento QA', 'tipo' => 'lista',
            'opciones' => [['etiqueta' => 'IT', 'activo' => true], ['etiqueta' => 'Finanzas', 'activo' => true]],
        ]))->assertSessionHasNoErrors();
        $campo = Campo::sole();
        [$it, $finanzas] = $campo->opciones->pluck('id')->all();
        $listado = route('admin.colaboradores.index');
        $datos = route('sistema.datos.lista', 'colaboradores');
        $presentacion = route('sistema.presentacion', ['modulo' => 'colaboradores']);
        $this->get(route('admin.colaboradores.create'))->assertOk()->assertSeeInOrder(['Departamento QA', 'Guardar']);
        $ana = ['codigo' => 'QA-ANA-12', 'nombre' => 'Ana  María Ríos', 'puesto' => 'Analista', 'estado' => 'Activo'];
        $luis = ['codigo' => 'QA-LUIS-34', 'nombre' => 'Luis Gómez', 'puesto' => 'Contador', 'estado' => 'Activo'];
        $this->post(route('admin.colaboradores.store'), $ana + [
            'campos_personalizados' => ['departamento' => $it],
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.colaboradores.store'), $luis + [
            'campos_personalizados' => ['departamento' => $finanzas],
        ])->assertSessionHasNoErrors();
        $this->get(route('admin.colaboradores.edit', $ana['codigo']))->assertOk()->assertSeeInOrder(['Departamento QA', 'Guardar']);
        $this->assertDatabaseHas('sistema_campo_valores', ['campo_id' => $campo->id, 'registro' => $ana['codigo'], 'busqueda' => (string) $it]);
        $this->get($listado)->assertOk()->assertSee('Departamento QA')->assertSeeInOrder([$ana['codigo'], '>IT</td>'], false);
        foreach ([$listado, $datos] as $ruta) {
            $this->get($ruta.'?'.http_build_query(['campo' => [$campo->id => $it]]))->assertOk()
                ->assertSee($ana['codigo'])->assertDontSee($luis['codigo']);
        }
        $this->get($datos.'?orden=campo_'.$campo->id.'&direccion=asc')->assertOk()->assertSeeInOrder([$luis['codigo'], $ana['codigo']]);
        $this->get($datos)->assertOk()->assertSee($presentacion);
        $this->get($presentacion)->assertOk()->assertSee('Departamento QA');
        $this->get(route('sistema.campos.editar', $campo))->assertOk()
            ->assertSeeInOrder(['Usar acceso del módulo', 'label="Colaboradores"', 'label="Incidencias para revisión de RR. HH."'], false);

        $this->put(route('sistema.presentacion.guardar', 'colaboradores'), [
            'columnas' => ['codigo' => ['orden' => 8, 'visible' => false, 'filtro' => false, 'buscar' => true]],
            'campos' => [$campo->id => ['orden' => 0, 'visible' => true]],
        ])->assertSessionHasNoErrors();
        $this->get($datos)->assertOk()->assertSeeInOrder(['Ordenar por Departamento QA', 'Ordenar por Código'], false);

        $this->put(route('admin.colaboradores.update', $ana['codigo']), $ana + [
            'campos_personalizados' => ['departamento' => $finanzas],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sistema_campo_valores', ['campo_id' => $campo->id, 'registro' => $ana['codigo'], 'busqueda' => (string) $finanzas]);
        $this->put(route('sistema.presentacion.guardar', 'colaboradores'), [
            'campos' => [$campo->id => ['orden' => 0, 'visible' => false]],
        ])->assertSessionHasNoErrors();
        foreach ([$listado, $datos] as $ruta) {
            $this->get($ruta)->assertOk()->assertDontSee('Ordenar por Departamento QA')->assertDontSee('>Departamento QA</th>', false);
        }
        $this->put(route('sistema.usuario.permisos', $this->super), [
            'reglas' => ['colaboradores.datos_exportar' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->super->fresh());
        $csv = $this->get(route('sistema.datos.exportar', 'colaboradores'))->assertDownload('colaboradores.csv')->streamedContent();
        $this->assertStringContainsString('Departamento QA', $csv);
        $this->assertStringContainsString('Finanzas', $csv);
    }

    public function test_datos_de_colaboradores_busca_por_nombre_parcial_espacios_y_codigo(): void
    {
        Colaborador::create(['codigo' => 'COD-QA-58', 'nombre' => 'Ana  María Ríos', 'puesto' => 'Analista', 'estado' => 'Activo']);
        Colaborador::create(['codigo' => 'COD-QA-65', 'nombre' => 'Otra Persona', 'puesto' => 'Analista', 'estado' => 'Activo']);
        $this->actingAs($this->admin);
        foreach (["  ANA\u{00A0} María\t Ríos  ", 'na Ríos', 'QA-58'] as $texto) {
            $this->get(route('sistema.datos.lista', ['modulo' => 'colaboradores', 'q' => $texto]))->assertOk()
                ->assertSee('COD-QA-58')->assertDontSee('COD-QA-65');
        }
    }

    public function test_inventario_agrupa_campos_por_condicion_y_limita_filtros_y_exportacion_a_la_bodega(): void
    {
        $this->superadministrador();
        $this->post(route('sistema.campos.guardar'), $this->definicion([
            'modulo' => 'inventario', 'clave' => 'pasillo', 'etiqueta' => 'Pasillo QA',
        ]))->assertSessionHasNoErrors();
        $campo = Campo::sole();
        $this->put(route('sistema.usuario.permisos', $this->super), [
            'reglas' => ['inventario.campos_editar' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->super->fresh());
        $producto = Producto::create(['codigo' => 'PRUEBA-PASILLO', 'nombre' => 'Artículo de prueba', 'categoria' => 'Equipo']);
        $ids = [];
        foreach ([
            [$this->regional->id, 'nuevo', 20, 'Estante nuevo'],
            [$this->regional->id, 'usado', 3, 'Estante usado'],
            [$this->principal->id, 'nuevo', 80, 'Solo central'],
        ] as [$bodega, $condicion, $cantidad, $valor]) {
            $id = DB::table('inventarios')->insertGetId([
                'producto_codigo' => $producto->codigo, 'bodega_id' => $bodega, 'stock_tipo' => $condicion, 'cantidad' => $cantidad,
            ]);
            $ids[$valor] = $id;
            $this->put(route('sistema.operacion.campos.actualizar', ['inventario', $id]), [
                'campos_personalizados' => ['pasillo' => $valor],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->put(route('sistema.usuario.permisos', $this->operador), [
            'reglas' => ['inventario.datos_exportar' => 'permitir'], 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sistema_campo_valores', ['campo_id' => $campo->id, 'registro' => (string) $ids['Estante nuevo'], 'busqueda' => 'Estante nuevo']);
        $respuesta = $this->actingAs($this->operador->fresh())->get(route('operador.bodegas.show', $this->regional))->assertOk();
        $this->assertSame('Nuevo: Estante nuevo · Usado: Estante usado', $respuesta->viewData('tablaCampos')['valoresPresentados']->get($producto->codigo)?->get($campo->id));
        $respuesta->assertSee('Nuevo: Estante nuevo')->assertSee('Usado: Estante usado')->assertDontSee('Solo central')
            ->assertViewHas('stockTotal', 23);
        $query = ['bodega' => $this->regional->id, 'campo' => [$campo->id => 'Estante usado']];
        $this->get(route('operador.bodegas.show', $query))->assertOk()->assertSee('Estante usado')
            ->assertDontSee('Estante nuevo')->assertViewHas('stockTotal', 3);
        $this->get(route('operador.bodegas.show', $this->principal))->assertForbidden();
        $export = $this->get(route('operador.bodegas.inventario.export', $query))->assertOk()->assertDownload();
        $excel = IOFactory::load($export->baseResponse->getFile()->getPathname());
        $this->assertSame('Pasillo QA', $excel->getActiveSheet()->getCell('K7')->getValue());
        $this->assertSame('Estante usado', $excel->getActiveSheet()->getCell('K8')->getValue());
        $this->assertSame(3, (int) $excel->getActiveSheet()->getCell('F8')->getValue());
        $this->assertDatabaseHas('inventarios', ['id' => $ids['Estante nuevo'], 'cantidad' => 20]);
        $this->assertDatabaseHas('inventarios', ['id' => $ids['Solo central'], 'cantidad' => 80]);
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
