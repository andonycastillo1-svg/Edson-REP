<?php

namespace Tests\Feature;

use App\Models\AsignacionInventario;
use App\Models\AsignacionVehiculo;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Operacion;
use App\Models\Producto;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoProductoAsignacion;
use App\Notifications\MovimientoInventarioNotification;
use App\Services\InventarioStockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class InventarioFlujosTest extends TestCase
{
    use RefreshDatabase;

    private Bodega $principal;
    private Bodega $regional;
    private User $admin;
    private User $operador;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
        config(['inventario.seguimiento_unidades' => true]);

        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Administrador'], ['id' => 2, 'nombre' => 'Encargado'],
        ]);
        $this->principal = Bodega::create(['nombre' => 'Central', 'tipo' => 'Principal']);
        $this->regional = Bodega::create(['nombre' => 'Regional', 'tipo' => 'Regional']);
        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->operador = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->regional->id]);
        $this->producto = Producto::create(['codigo' => 'P-1', 'nombre' => 'Filtro', 'categoria' => 'Refacciones', 'vida_util_meses' => 6]);
        Colaborador::create(['codigo' => 'C-1', 'nombre' => 'Persona', 'puesto' => 'Operación', 'estado' => 'Activo']);
    }

    public function test_compra_confirma_notifica_y_un_reintento_no_duplica_stock(): void
    {
        Storage::fake('documentos');
        $this->actingAs($this->admin);
        $central = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->principal->id]);
        $data = $this->compra();
        $ingreso = now()->subDay()->startOfDay();
        $data['fecha_ingreso'] = $ingreso->toDateTimeString();
        $data['pdfs'] = [UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf')];

        $this->post(route('admin.compras.store'), $data)->assertRedirect(route('admin.compras.index'));
        $id = DB::table('compras')->value('id');
        $this->post(route('admin.compras.store'), $data)->assertRedirect(route('admin.compras.show', $id));

        Notification::assertSentToTimes($central, MovimientoInventarioNotification::class, 1);
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('movimientos', 1);
        $this->assertDatabaseHas('compras', ['id' => $id, 'total_factura' => 0.30]);
        $this->assertDatabaseHas('inventarios', ['bodega_id' => $this->principal->id, 'stock_tipo' => 'nuevo', 'cantidad' => 3]);
        $this->assertDatabaseCount('inventario_existencias', 3);
        $this->assertDatabaseHas('inventario_existencias', ['ingresado_en' => $ingreso->toDateTimeString()]);
        $this->assertDatabaseHas('movimientos', ['tipo_movimiento' => 'Entrada', 'fecha' => $ingreso->toDateTimeString()]);
        $archivo = DB::table('compra_archivos')->first();
        Storage::disk('documentos')->assertExists($archivo->ruta);
        $this->assertCount(1, Storage::disk('documentos')->allFiles());
        $this->get(route('admin.compras.archivo', ['compra' => $id, 'archivo' => $archivo->id]))->assertOk();
        $this->actingAs($this->operador)->get(route('operador.compras.archivo', ['compra' => $id, 'archivo' => $archivo->id]))->assertForbidden();
    }

    public function test_compra_regional_y_lineas_incompletas_se_rechazan_sin_escrituras(): void
    {
        $data = $this->compra();
        $this->actingAs($this->operador)->postJson(route('operador.compras.store'), $data)->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('admin.compras.store'), [...$data, 'cantidad' => [3, 4]])
            ->assertUnprocessable()->assertJsonValidationErrors('cantidad');

        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('proveedores', 0);
    }

    public function test_devolucion_parcial_historica_es_idempotente_y_el_pdf_retiene_cantidad(): void
    {
        $this->actingAs($this->admin);
        $this->agregarStock($this->principal, 2);
        $asignacion = $this->custodiaHistorica($this->principal, 2);
        $ruta = route('admin.asignaciones.devolver', $asignacion);

        $devolucion = ['solicitud_id' => (string) Str::uuid(), 'cantidad_devuelta' => 1, 'estado_devolucion' => 'buen_estado'];
        $this->post($ruta, $devolucion)->assertSessionHasNoErrors();
        $this->postJson($ruta, $devolucion)->assertUnprocessable()->assertJsonValidationErrors('solicitud_id');
        $this->postJson(route('admin.asignaciones.devolver_lote'), [
            'solicitud_id' => $devolucion['solicitud_id'], 'estado_devolucion' => 'buen_estado',
            'seleccionadas' => [$asignacion->id], 'devoluciones' => [$asignacion->id => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors('solicitud_id');
        $this->assertDatabaseHas('inventarios', ['bodega_id' => $this->principal->id, 'stock_tipo' => 'usado', 'cantidad' => 1]);
        $this->assertDatabaseHas('inventarios', ['bodega_id' => $this->principal->id, 'stock_tipo' => 'nuevo', 'cantidad' => 0]);
        $this->assertSame(1, $asignacion->fresh()->cantidad_asignada);

        $this->post($ruta, [...$devolucion, 'solicitud_id' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->get(route('admin.asignaciones.pdf', $asignacion->grupo_asignacion))->assertOk()
            ->assertViewHas('asignaciones', fn ($filas) => (int) $filas->first()->cantidad === 2);
        $this->assertDatabaseHas('inventarios', ['stock_tipo' => 'usado', 'cantidad' => 2]);
        $this->assertDatabaseCount('inventario_existencias', 2);
        $this->assertDatabaseCount('asignacion_movimientos', 3);
    }

    public function test_operador_puede_asignar_el_mismo_codigo_sin_poder_resolver_custodia_ajena(): void
    {
        $this->actingAs($this->admin);
        $this->agregarStock($this->regional, 2);
        $this->post(route('admin.asignaciones.store'), $this->asignacion($this->regional))->assertSessionHasNoErrors();
        $anterior = AsignacionInventario::sole();

        $this->actingAs($this->operador)->post(route('operador.asignaciones.store'), $this->asignacion($this->regional))
            ->assertSessionHasNoErrors();
        $this->postJson(route('operador.asignaciones.devolver', $anterior), [
            'solicitud_id' => (string) Str::uuid(), 'cantidad_devuelta' => 1, 'estado_devolucion' => 'buen_estado',
        ])->assertForbidden();

        $this->assertDatabaseCount('asignaciones_inventarios', 2);
        $this->assertSame('Activa', $anterior->fresh()->estado);
        $this->assertDatabaseCount('asignacion_movimientos', 2);
    }

    public function test_nueva_salida_conserva_saldo_historico_completo_como_custodia_anterior(): void
    {
        $this->actingAs($this->operador);
        $this->agregarStock($this->regional, 3);
        $anterior = $this->custodiaHistorica($this->regional, 2);
        $this->post(route('operador.asignaciones.store'), [...$this->asignacion($this->regional), 'fecha' => now()->toDateTimeString()])->assertSessionHasNoErrors();
        $nueva = AsignacionInventario::custodiaActiva()->sole();

        $this->assertDatabaseHas('asignaciones_inventarios', [
            'id' => $anterior->id, 'estado' => 'Activa', 'cantidad_asignada' => 2, 'sustituida_por_id' => $nueva->id,
        ]);
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        $this->assertDatabaseHas('alertas_reemplazos_rrhh', [
            'asignacion_anterior_id' => $anterior->id, 'motivo_revision' => 'reemplazo_sin_datos',
        ]);
        $this->artisan('inventario:contrastar-saldos')->assertExitCode(0);
    }

    public function test_documentos_de_devolucion_conservan_version_anterior_en_disco_privado(): void
    {
        Storage::fake('documentos');
        $this->actingAs($this->admin);
        $this->agregarStock($this->principal, 1);
        $this->post(route('admin.asignaciones.store'), $this->asignacion($this->principal))->assertSessionHasNoErrors();
        $asignacion = AsignacionInventario::sole();
        $this->post(route('admin.asignaciones.devolver', $asignacion), ['solicitud_id' => (string) Str::uuid(), 'cantidad_devuelta' => 1, 'estado_devolucion' => 'buen_estado', 'motivo_administrativo' => 'renuncia'])->assertSessionHasNoErrors();

        $grupo = DB::table('asignacion_movimientos')->where('tipo', 'Devolucion')->value('grupo_devolucion');
        $ruta = route('admin.asignaciones.upload_pdf_devolucion_firmado', $grupo);
        foreach (['primero.pdf', 'segundo.pdf'] as $nombre) {
            $this->post($ruta, ['pdf_firmado' => UploadedFile::fake()->create($nombre, 4, 'application/pdf')])
                ->assertSessionHasNoErrors();
        }

        $archivos = DB::table('asignacion_inventario_archivos')->orderBy('id')->get();
        $this->assertCount(2, $archivos);
        foreach ($archivos as $archivo) {
            Storage::disk('documentos')->assertExists($archivo->ruta);
            $this->get(route('admin.asignaciones.ver_pdf_firmado', $archivo->id))->assertOk();
        }
        $this->actingAs($this->operador)->get(route('operador.asignaciones.ver_pdf_firmado', $archivos->first()->id))->assertForbidden();
    }

    public function test_una_diferencia_en_existencias_no_sobrescribe_el_resumen(): void
    {
        $this->actingAs($this->admin);
        DB::table('inventarios')->insert([
            'bodega_id' => $this->principal->id, 'producto_codigo' => $this->producto->codigo,
            'stock_tipo' => 'nuevo', 'cantidad' => 2,
        ]);

        $this->postJson(route('admin.asignaciones.store'), $this->asignacion($this->principal))
            ->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertDatabaseCount('inventario_existencias', 0);
        $this->assertDatabaseCount('asignaciones_inventarios', 0);
        $this->assertDatabaseHas('inventarios', ['stock_tipo' => 'nuevo', 'cantidad' => 2]);
    }

    public function test_traslado_solo_admite_una_decision_y_conserva_las_unidades(): void
    {
        $this->actingAs($this->admin);
        $this->agregarStock($this->principal, 2);
        $this->post(route('admin.operaciones.traslados.store'), [
            'bodega_origen_id' => $this->principal->id,
            'bodega_destino_id' => $this->regional->id,
            'lineas' => [['producto_codigo' => $this->producto->codigo, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();
        $traslado = Operacion::sole();
        $aprobar = route('operador.operaciones.traslados.aprobar', $traslado);

        $this->actingAs($this->operador)->post($aprobar)->assertSessionHasNoErrors();
        $this->postJson($aprobar)->assertUnprocessable();
        $this->postJson(route('operador.operaciones.traslados.rechazar', $traslado), ['motivo_rechazo' => 'Repetición'])->assertUnprocessable();

        $this->assertDatabaseCount('movimientos', 1);
        $this->assertDatabaseHas('operaciones', ['id' => $traslado->id, 'estado' => Operacion::ESTADO_APROBADO]);
        $this->assertDatabaseHas('inventarios', ['bodega_id' => $this->regional->id, 'stock_tipo' => 'nuevo', 'cantidad' => 1]);
        $this->assertDatabaseHas('inventario_existencias', ['bodega_id' => $this->regional->id, 'condicion' => 'nuevo']);
        $this->assertDatabaseCount('inventario_existencias', 2);
    }

    public function test_desasignar_vehiculo_rechaza_refacciones_ajenas_y_conserva_cierre_parcial(): void
    {
        $this->actingAs($this->admin);
        $this->agregarStock($this->principal, 3);
        Vehiculo::create(['vin' => 'VIN-1', 'placa' => 'P1']);
        Vehiculo::create(['vin' => 'VIN-2', 'placa' => 'P2']);

        $this->post(route('admin.vehiculos.asignaciones.store'), [
            'vehiculo_vin' => 'VIN-1', 'colaborador_codigo' => 'C-1',
            'fecha_inicio' => today()->toDateString(), 'estado_inicial_vehiculo' => 'Disponible',
            'productos' => [['producto_codigo' => $this->producto->codigo, 'bodega_id' => $this->principal->id, 'cantidad' => 2]],
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.vehiculos.productos.store'), [
            'vehiculo_vin' => 'VIN-2', 'producto_codigo' => $this->producto->codigo,
            'bodega_id' => $this->principal->id, 'cantidad' => 1, 'fecha' => today()->toDateString(), 'motivo' => 'Prueba',
        ])->assertSessionHasNoErrors();
        $vehiculo = AsignacionVehiculo::sole();
        $ruta = route('admin.vehiculos.asignaciones.desasignar', $vehiculo);
        $cierre = ['fecha_fin' => today()->toDateString(), 'estado_final_vehiculo' => 'Disponible'];
        $ajena = VehiculoProductoAsignacion::where('vehiculo_vin', 'VIN-2')->sole();
        $propia = VehiculoProductoAsignacion::where('vehiculo_vin', 'VIN-1')->sole();

        $data = [...$cierre, 'productos' => [['id' => $ajena->id, 'accion' => 'regresar', 'cantidad' => 1]]];
        $this->postJson($ruta, $data)->assertUnprocessable()->assertJsonValidationErrors('productos');
        $this->assertTrue($vehiculo->fresh()->activa);
        $this->assertTrue($ajena->fresh()->activa);

        $data['productos'][0]['id'] = $propia->id;
        $this->post($ruta, $data)->assertSessionHasNoErrors();
        $this->postJson($ruta, $data)->assertUnprocessable();

        $this->assertDatabaseHas('vehiculo_producto_asignaciones', ['id' => $propia->id, 'activa' => true, 'cantidad' => 1]);
        $this->assertDatabaseHas('inventarios', ['stock_tipo' => 'usado', 'cantidad' => 1]);
        $this->assertDatabaseCount('inventario_existencias', 3);
    }

    public function test_bodega_y_excel_usan_un_costo_por_producto_en_compras_del_mismo_dia(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.compras.store'), $this->compra())->assertSessionHasNoErrors();
        $this->post(route('admin.compras.store'), [...$this->compra(), 'no_factura' => 'F-2', 'precio_unitario' => ['0.20']])
            ->assertSessionHasNoErrors();

        DB::table('inventarios')->insert([
            ['producto_codigo' => $this->producto->codigo, 'bodega_id' => $this->principal->id, 'stock_tipo' => 'usado', 'cantidad' => 2],
            ['producto_codigo' => $this->producto->codigo, 'bodega_id' => $this->principal->id, 'stock_tipo' => 'danado', 'cantidad' => 1],
        ]);
        $this->get(route('admin.bodegas.show', $this->principal))->assertOk()
            ->assertViewHas('stockTotal', 8)->assertViewHas('productosTotal', 1)
            ->assertViewHas('costoTotalInventario', fn ($costo) => abs($costo - 1.60) < 0.001);
        $this->get(route('admin.bodegas.show', ['bodega' => $this->principal, 'stock_tipo' => 'danado']))->assertOk()
            ->assertViewHas('stockTotal', 1);
        $this->get(route('admin.bodegas.inventario.export', $this->principal))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_los_paneles_por_rol_y_el_formulario_de_asignacion_renderizan(): void
    {
        DB::table('roles')->insert([['id' => 3, 'nombre' => 'Supervisor'], ['id' => 4, 'nombre' => 'RRHH']]);
        $supervisor = User::factory()->create(['role_id' => 3]);
        $rrhh = User::factory()->create(['role_id' => 4]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.asignaciones.create'))->assertOk();
        $this->actingAs($this->operador)->get(route('operador.dashboard'))->assertOk();
        $this->actingAs($supervisor)->get(route('supervisor.dashboard'))->assertOk();
        $this->actingAs($rrhh)->get(route('rrhh.dashboard'))->assertOk();
    }

    private function agregarStock(Bodega $bodega, int $cantidad): void
    {
        DB::transaction(fn () => app(InventarioStockService::class)->incrementar($bodega->id, $this->producto->codigo, $cantidad));
    }

    private function compra(): array
    {
        return [
            'fecha_compra' => today()->toDateString(), 'no_factura' => 'F-1', 'proveedor_tipo' => 'nuevo',
            'proveedor_nombre' => 'Pruebas', 'forma_pago' => 'Al contado', 'producto_tipo' => ['existente'],
            'producto_codigo' => [$this->producto->codigo], 'cantidad' => [3], 'precio_unitario' => ['0.10'],
        ];
    }

    private function custodiaHistorica(Bodega $bodega, int $cantidad): AsignacionInventario
    {
        return DB::transaction(function () use ($bodega, $cantidad) {
            $asignacion = AsignacionInventario::create([
                'grupo_asignacion' => (string) Str::uuid(), 'colaborador_codigo' => 'C-1',
                'producto_codigo' => $this->producto->codigo, 'bodega_id' => $bodega->id,
                'cantidad_asignada' => $cantidad, 'user_id' => auth()->id(), 'fecha' => now(),
            ]);
            app(InventarioStockService::class)->asignar($asignacion, 'nuevo');
            $asignacion->movimientos()->create([
                'tipo' => 'Asignacion', 'cantidad' => $cantidad, 'user_id' => auth()->id(),
            ]);

            return $asignacion;
        });
    }

    private function asignacion(Bodega $bodega, int $cantidad = 1): array
    {
        return [
            'solicitud_id' => (string) Str::uuid(), 'colaborador_codigo' => 'C-1', 'fecha' => today()->toDateString(),
            'aprobado_por' => 'Gerencia', 'medio_solicitud' => 'Correo',
            'items' => [['producto_codigo' => $this->producto->codigo, 'bodega_id' => $bodega->id,
                'cantidad_asignada' => $cantidad, 'stock_tipo' => 'nuevo']],
        ];
    }
}
