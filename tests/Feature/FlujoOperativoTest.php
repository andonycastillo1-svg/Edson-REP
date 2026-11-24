<?php

namespace Tests\Feature;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Consolidado;
use App\Models\ConstanciaGerencia;
use App\Models\ImportacionAsignacion;
use App\Models\Inspeccion;
use App\Models\Producto;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\ExcelAsignaciones;
use App\Services\InventarioStockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class FlujoOperativoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $central;
    private User $regional;
    private User $supervisor;
    private User $coordinador;
    private User $ajeno;
    private Bodega $bodega;
    private Region $norte;
    private Region $sur;
    private int $articulo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inventario.seguimiento_unidades' => false]);
        Storage::fake('documentos');
        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Administrador'], ['id' => 2, 'nombre' => 'Almacenista'],
            ['id' => 3, 'nombre' => 'Supervisor'], ['id' => 4, 'nombre' => 'Recursos Humanos'],
        ]);
        $rol = Role::where('ambito_operativo', 5)->sole();
        $this->bodega = Bodega::create(['nombre' => 'Central', 'tipo' => 'Principal']);
        $regional = Bodega::create(['nombre' => 'Sucursal', 'tipo' => 'Regional']);
        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->central = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->bodega->id]);
        $this->regional = User::factory()->create(['role_id' => 2, 'bodega_id' => $regional->id]);
        $this->coordinador = User::factory()->create(['role_id' => $rol->id]);
        $this->ajeno = User::factory()->create(['role_id' => $rol->id]);
        $this->supervisor = User::factory()->create(['role_id' => 3, 'coordinador_id' => $this->coordinador->id]);
        $this->supervisor->almacenistasAsignados()->attach($this->regional);
        $this->norte = Region::create(['nombre' => 'Norte', 'activa' => true]);
        $this->sur = Region::create(['nombre' => 'Sur', 'activa' => true]);
        Colaborador::create(['codigo' => 'COL-001', 'nombre' => 'Técnica norte', 'puesto' => 'Instalación', 'estado' => 'Activo', 'region_id' => $this->norte->id]);
        Colaborador::create(['codigo' => 'COL-002', 'nombre' => 'Técnico sur', 'puesto' => 'Mantenimiento', 'estado' => 'Activo', 'region_id' => $this->sur->id]);
        Producto::create(['codigo' => 'P-001', 'nombre' => 'Arnés QA', 'vida_util_meses' => 2]);
        Producto::create(['codigo' => 'P-002', 'nombre' => 'Casco QA', 'vida_util_meses' => 2]);
        $this->articulo = DB::table('inspeccion_articulos')->insertGetId([
            'seccion' => 'rescate', 'producto_codigo' => 'P-001', 'etiqueta' => 'Arnés', 'maximo' => 20, 'activo' => true,
        ]);
    }

    public function test_catalogos_y_usuarios_asocian_coordinacion_sin_cambiar_almacenista(): void
    {
        $this->actingAs($this->admin)->get(route('flujo.catalogos'))->assertOk()->assertSee('Cinta reflectiva')->assertSee('Robo injustificado')->assertDontSee('Máximo por inspección');
        $this->post(route('flujo.catalogos.producto'), ['codigo' => 'P-NUEVO', 'nombre' => 'Equipo de inspección'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['codigo' => 'P-NUEVO']);
        $this->assertDatabaseCount('inventarios', 0);
        $this->post(route('flujo.catalogos.region'), ['nombre' => 'Centro', 'activa' => 1])->assertSessionHasNoErrors();
        $this->post(route('flujo.catalogos.articulo'), [
            'seccion' => 'comun', 'producto_codigo' => 'P-002', 'etiqueta' => 'Casco', 'descripcion' => 'De seguridad', 'orden' => 1, 'activo' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inspeccion_articulos', ['producto_codigo' => 'P-002', 'etiqueta' => 'Casco', 'maximo' => 0]);
        $this->post(route('flujo.catalogos.articulo', $this->articulo), [
            'seccion' => 'rescate', 'producto_codigo' => 'P-001', 'etiqueta' => 'Arnés actualizado', 'orden' => 1, 'activo' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inspeccion_articulos', ['id' => $this->articulo, 'etiqueta' => 'Arnés actualizado', 'maximo' => 20]);
        $this->get(route('admin.usuarios.edit', $this->supervisor))->assertOk()->assertSee('Coordinador de requerimientos');
        $data = [
            'name' => $this->supervisor->name, 'email' => $this->supervisor->email, 'role_id' => 3,
            'almacenista_id' => $this->regional->id, 'coordinador_id' => $this->regional->id,
        ];
        $this->putJson(route('admin.usuarios.update', $this->supervisor), $data)->assertUnprocessable()->assertJsonValidationErrors('coordinador_id');
        $this->put(route('admin.usuarios.update', $this->supervisor), [...$data, 'coordinador_id' => $this->ajeno->id])->assertSessionHasNoErrors();
        $this->assertEquals($this->ajeno->id, $this->supervisor->fresh()->coordinador_id);
        $this->assertEquals($this->regional->id, $this->supervisor->almacenistasAsignados()->sole()->id);
        $this->actingAs($this->ajeno)->get(route('dashboard'))->assertRedirect(route('coordinacion.dashboard'));
        $this->get(route('coordinacion.dashboard'))->assertOk();
        $this->get(route('flujo.catalogos'))->assertForbidden();
    }

    public function test_inspeccion_filtra_por_region_registra_puesto_y_protege_fotos(): void
    {
        $this->actingAs($this->supervisor)->get(route('flujo.inspecciones.create'))->assertOk()
            ->assertSee('Equipo de rescate en alturas titular')->assertSee('Fotografías');
        $this->getJson(route('flujo.inspecciones.colaboradores', $this->norte))->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.codigo', 'COL-001')->assertJsonPath('0.puesto', 'Instalación');
        $this->postJson(route('flujo.inspecciones.store'), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha', 'region_id', 'colaborador_codigo']);
        $this->postJson(route('flujo.inspecciones.store'), [...$this->datosInspeccion(), 'region_id' => $this->sur->id])
            ->assertUnprocessable()->assertJsonValidationErrors('colaborador_codigo');
        $this->postJson(route('flujo.inspecciones.store'), [...$this->datosInspeccion(), 'cantidades' => [$this->articulo => -1]])
            ->assertUnprocessable()->assertJsonValidationErrors('cantidades.'.$this->articulo);
        $request = $this->datosInspeccion(12);
        $this->post(route('flujo.inspecciones.store'), [...$request, 'supervisor_id' => $this->ajeno->id,
            'fotos' => [UploadedFile::fake()->image('inspeccion.png')]])->assertSessionHasNoErrors();
        $this->postJson(route('flujo.inspecciones.store'), $request)->assertUnprocessable()->assertJsonValidationErrors('solicitud_id');
        $fila = Inspeccion::sole();
        $this->assertEquals($this->supervisor->id, $fila->supervisor_id);
        $this->assertSame('Instalación', $fila->puesto);
        $this->assertEquals(12, $fila->lineas()->sole()->cantidad);
        $foto = $fila->fotos()->sole();
        Storage::disk('documentos')->assertExists($foto->ruta);
        $url = route('flujo.inspecciones.foto', [$fila, $foto]);
        $this->get($url)->assertOk();
        $this->actingAs($this->coordinador)->get($url)->assertOk();
        $this->actingAs($this->ajeno)->get($url)->assertNotFound();
        $otro = User::factory()->create(['role_id' => 3, 'coordinador_id' => $this->coordinador->id]);
        $this->actingAs($otro)->get(route('flujo.inspecciones.show', $fila))->assertNotFound();
        $this->actingAs($this->regional)->get(route('flujo.inspecciones.index'))->assertForbidden();
        $this->assertDatabaseCount('inventarios', 0);
    }

    public function test_inspeccion_acepta_cantidades_enteras_desde_cero_sin_usar_el_maximo_historico(): void
    {
        DB::table('inspeccion_articulos')->where('id', $this->articulo)->update(['seccion' => 'reflectivos', 'maximo' => 7]);
        $this->actingAs($this->supervisor)->get(route('flujo.inspecciones.create'))->assertOk()
            ->assertSee('type="number" name="cantidades['.$this->articulo.']" min="0" step="1"', false)
            ->assertDontSee('max="7"', false);
        $url = route('flujo.inspecciones.store');
        $this->postJson($url, $this->datosInspeccion(0))->assertRedirect();
        $this->assertDatabaseCount('inspeccion_lineas', 0);

        foreach ([10, 50, 100, 65536] as $cantidad) {
            $this->post($url, $this->datosInspeccion($cantidad))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('inspeccion_lineas', ['producto_codigo' => 'P-001', 'cantidad' => $cantidad]);
        }
        foreach ([-1, '1.5', true] as $invalida) {
            $this->postJson($url, [...$this->datosInspeccion(), 'cantidades' => [$this->articulo => $invalida]])
                ->assertUnprocessable()->assertJsonValidationErrors('cantidades.'.$this->articulo);
        }
        $this->assertDatabaseCount('inspecciones', 5);
        $this->assertDatabaseCount('inspeccion_lineas', 4);
        $this->assertDatabaseCount('inventarios', 0);
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_coordinador_edita_y_agrega_cantidades_sin_el_tope_anterior(): void
    {
        $this->stock('P-001', 3);
        $this->actingAs($this->supervisor);
        foreach ([4294967296, 50] as $cantidad) {
            $this->post(route('flujo.inspecciones.store'), $this->datosInspeccion($cantidad))->assertSessionHasNoErrors();
        }
        $datos = ['nombre' => 'Equipos de campo', 'desde' => today()->toDateString(), 'hasta' => today()->toDateString(),
            'inspecciones' => Inspeccion::pluck('id')->all()];
        $this->actingAs($this->central)->post(route('flujo.consolidados.store'), $datos)->assertSessionHasNoErrors();
        $consolidado = Consolidado::sole();
        $linea = $consolidado->lineas()->sole();
        $this->assertEquals(4294967346, $linea->original);
        $this->estado($consolidado, 'enviar');

        $this->actingAs($this->coordinador)->get(route('flujo.consolidados.show', $consolidado))->assertOk()
            ->assertSee('name="cantidad_nueva"', false)->assertDontSee('max="2000000"', false);
        $url = route('flujo.consolidados.transicion', [$consolidado, 'ajustar']);
        $version = $consolidado->eventos()->max('id');
        $this->postJson($url, ['version' => $version, 'nota' => 'Ajuste de cantidades',
            'cantidades' => [$linea->id => -1], 'producto_codigo' => 'P-002', 'cantidad_nueva' => '1.5'])
            ->assertUnprocessable()->assertJsonValidationErrors(['cantidades.'.$linea->id, 'cantidad_nueva']);
        $this->estado($consolidado, 'ajustar', ['cantidades' => [$linea->id => 0], 'producto_codigo' => 'P-002',
            'cantidad_nueva' => 4294967296, 'nota' => 'Redistribución por producto']);
        $this->assertEquals(0, $linea->fresh()->cantidad);
        $this->assertDatabaseHas('consolidado_lineas', ['consolidado_id' => $consolidado->id, 'producto_codigo' => 'P-002',
            'original' => 0, 'cantidad' => 4294967296]);
        $this->estado($consolidado, 'aprobar', ['cantidades' => [$linea->id => 4294967300], 'nota' => 'Cantidad definitiva']);
        $this->assertDatabaseHas('consolidado_lineas', ['id' => $linea->id, 'original' => 4294967346, 'cantidad' => 4294967300]);
        $this->assertEquals(4294967346, DB::table('inspeccion_lineas')->sum('cantidad'));
        $this->assertSame(3, $this->saldo('P-001'));
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_consolidar_conserva_las_inspecciones_si_la_suma_no_cabe_en_el_almacenamiento(): void
    {
        $this->actingAs($this->supervisor);
        foreach ([PHP_INT_MAX, 1] as $cantidad) {
            $this->postJson(route('flujo.inspecciones.store'), $this->datosInspeccion($cantidad))->assertRedirect();
        }
        $this->actingAs($this->central)->postJson(route('flujo.consolidados.store'), [
            'nombre' => 'Equipos de campo', 'desde' => today()->toDateString(), 'hasta' => today()->toDateString(),
            'inspecciones' => Inspeccion::pluck('id')->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors('inspecciones');
        $this->assertDatabaseCount('consolidados', 0);
        $this->assertDatabaseCount('consolidado_inspeccion', 0);
        $this->assertDatabaseHas('inspeccion_lineas', ['producto_codigo' => 'P-001', 'cantidad' => PHP_INT_MAX]);
        $this->assertDatabaseCount('inspeccion_lineas', 2);
    }

    public function test_consolidado_suma_ajusta_aprueba_y_conserva_inspecciones_e_historial(): void
    {
        $this->actingAs($this->supervisor);
        foreach ([2, 3] as $cantidad) {
            $this->post(route('flujo.inspecciones.store'), $this->datosInspeccion($cantidad))->assertSessionHasNoErrors();
        }
        $this->actingAs($this->central)->get(route('flujo.inspecciones.index', ['region' => $this->norte->id]))
            ->assertOk()->assertSee('Técnica norte');
        $datos = ['nombre' => 'Noviembre', 'desde' => today()->toDateString(), 'hasta' => today()->toDateString(),
            'inspecciones' => Inspeccion::pluck('id')->all()];
        $this->post(route('flujo.consolidados.store'), $datos)->assertSessionHasNoErrors();
        $this->postJson(route('flujo.consolidados.store'), $datos)->assertUnprocessable()->assertJsonValidationErrors('inspecciones');
        $consolidado = Consolidado::sole();
        $linea = $consolidado->lineas()->sole();
        $this->assertEquals(5, $linea->original);
        $this->get(route('flujo.consolidados.show', $consolidado))->assertOk()->assertSee('Enviar a coordinación');
        $this->estado($consolidado, 'enviar');
        $this->actingAs($this->ajeno)->get(route('flujo.consolidados.show', $consolidado))->assertNotFound();
        $this->actingAs($this->coordinador)->get(route('flujo.consolidados.show', $consolidado))
            ->assertOk()->assertSee('Aprobar consolidado')->assertSee('Solicitudes originales');
        $url = route('flujo.consolidados.transicion', [$consolidado, 'aprobar']);
        $version = $consolidado->eventos()->max('id');
        $this->postJson($url, ['version' => $version, 'cantidades' => [$linea->id => 6]])->assertUnprocessable()->assertJsonValidationErrors('nota');
        $this->estado($consolidado, 'ajustar', ['cantidades' => [$linea->id => 6], 'producto_codigo' => 'P-002',
            'cantidad_nueva' => 2, 'nota' => 'Ajuste por nuevas cuadrillas.']);
        $this->postJson($url, ['version' => $version])->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->estado($consolidado, 'aprobar');
        $this->assertSame('aprobado', $consolidado->refresh()->estado);
        $this->assertEquals($this->coordinador->id, $consolidado->aprobado_por);
        $this->assertEquals(6, $linea->refresh()->cantidad);
        $this->assertEquals(5, $linea->original);
        $this->assertEquals(5, DB::table('inspeccion_lineas')->sum('cantidad'));
        $evento = $consolidado->eventos()->where('accion', 'ajustar')->sole();
        $this->assertEquals($this->coordinador->id, $evento->user_id);
        $this->assertEquals(['producto' => 'P-001', 'antes' => 5, 'nuevo' => 6], $evento->cambios['productos'][0]);
        $this->postJson(route('flujo.consolidados.transicion', [$consolidado, 'ajustar']), [
            'version' => $consolidado->eventos()->max('id'), 'cantidades' => [$linea->id => 8], 'nota' => 'Intento posterior',
        ])->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertDatabaseCount('inventarios', 0);
    }

    public function test_plan_usa_saldo_central_constancia_privada_y_compra_existente_sin_duplicar(): void
    {
        $a = $this->aprobado('Consolidado A', 4);
        $b = $this->aprobado('Consolidado B', 3);
        $this->stock('P-001', 2);
        $this->actingAs($this->central)->get(route('flujo.compras.index'))->assertOk()
            ->assertViewHas('lineas', fn ($filas) => $filas->sum('sugerida') === 5);
        $this->post(route('flujo.compras.constancia'), [
            'consolidados' => [$a->id, $b->id], 'medio' => 'Correo', 'fecha' => today()->toDateString(), 'autorizo' => 'Gerencia QA',
            'referencia' => 'Correo AF-01', 'propuestas' => [
                $a->lineas()->sole()->id => ['existencia' => 1, 'compra' => 3],
                $b->lineas()->sole()->id => ['existencia' => 0, 'compra' => 3],
            ],
            'documento' => UploadedFile::fake()->create('autorizacion.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $constancia = ConstanciaGerencia::sole();
        $this->assertEquals(6, array_sum(array_column($constancia->plan, 'propuesta')));
        $this->assertEquals(2, $constancia->consolidados()->count());
        $this->assertSame(2, $this->saldo('P-001'));
        $url = route('flujo.compras.documento', $constancia);
        $this->get($url)->assertOk();
        $this->actingAs($this->coordinador)->get($url)->assertForbidden();

        $factura = [
            'fecha_compra' => today()->toDateString(), 'no_factura' => 'QA-100', 'proveedor_tipo' => 'nuevo',
            'proveedor_nombre' => 'Proveedor flujo', 'autorizado_por' => 'Gerencia QA', 'forma_pago' => 'Al contado', 'producto_tipo' => ['existente'],
            'producto_codigo' => ['P-001'], 'cantidad' => [5], 'precio_unitario' => ['12.00'],
            'consolidados' => [$a->id, $b->id],
            'distribucion' => [$a->id => [bin2hex('P-001') => 2], $b->id => [bin2hex('P-001') => 3]],
        ];
        $ruta = route('operador.compras.store');
        $this->actingAs($this->central)->post($ruta, $factura)->assertSessionHasNoErrors();
        $this->post($ruta, $factura)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('compra_consolidado', 2);
        $this->assertDatabaseCount('movimientos', 1);
        $this->assertSame(7, $this->saldo('P-001'));
        $this->actingAs($this->admin)->postJson(route('flujo.consolidados.transicion', [$a, 'reabrir']), [
            'version' => $a->eventos()->max('id'), 'nota' => 'Cambiar alcance',
        ])->assertUnprocessable()->assertJsonValidationErrors('estado');
    }


    public function test_compras_parciales_por_proveedor_conservan_el_plan_y_el_saldo_actual_independientes(): void
    {
        $consolidado = $this->aprobado('Dotación de noviembre', 22);
        $linea = $consolidado->lineas()->sole();
        $this->stock('P-001', 2);
        $this->actingAs($this->central)->post(route('flujo.compras.constancia'), $this->datosConstancia($consolidado, [
            $linea->id => ['existencia' => 2, 'compra' => 20],
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('movimientos', 0);

        $ruta = route('operador.compras.store');
        $factura = $this->datosCompra('F-10', ['P-001'], [10], [
            'consolidados' => [$consolidado->id], 'distribucion' => [$consolidado->id => [bin2hex('P-001') => 10]],
            'pdfs' => [UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf')],
        ]);
        $this->post($ruta, $factura)->assertSessionHasNoErrors();
        $this->assertSame(12, $this->saldo('P-001'));
        $this->get(route('operador.compras.index'))->assertOk()->assertSee('F-10')->assertSee('10 pendientes');

        $this->postJson(route('flujo.consolidados.transicion', [$consolidado, 'cerrar']), [
            'version' => $consolidado->eventos()->max('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->get(route('operador.compras.create', ['consolidados' => [$consolidado->id]]))->assertOk()
            ->assertSee('10 pendientes')->assertSee('name="distribucion[', false);
        $url = route('flujo.compras.index', ['consolidados' => [$consolidado->id]]);
        $this->get($url)->assertOk()->assertSee('Registrar nueva compra')->assertSee('F-10')->assertSee('PROVEEDOR A')
            ->assertViewHas('lineas', fn ($filas) => $filas->sole()['sugerida'] === 10 && $filas->sole()['central'] === 12);
        $compra = DB::table('compras')->sole();
        $adjunto = DB::table('compra_archivos')->sole();
        $this->get(route('operador.compras.archivo', [$compra->id, $adjunto->id]))->assertOk();

        // Una salida posterior cambia el saldo actual y deja intacta la ejecución registrada.
        DB::transaction(fn () => app(InventarioStockService::class)->trasladar($this->bodega->id, $this->regional->bodega_id, 'P-001', 11));
        $this->get($url)->assertOk()->assertViewHas('lineas', function ($filas) {
            $fila = $filas->sole();

            return $fila['considerada'] === 2 && $fila['decidida'] === 20 && $fila['comprada'] === 10
                && $fila['pendiente'] === 10 && $fila['central'] === 1;
        });
        $this->postJson(route('flujo.compras.constancia'), $this->datosConstancia($consolidado, [
            $linea->id => ['existencia' => 2, 'compra' => 9],
        ]))->assertUnprocessable()->assertJsonValidationErrors('propuestas');

        $otra = [...$factura, 'proveedor_nombre' => 'Proveedor B', 'pdfs' => [], 'fecha_compra' => today()->subDays(1)->toDateString()];
        $this->post($ruta, $otra)->assertSessionHasNoErrors();
        $this->post($ruta, $otra)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('compras', 2);
        $this->assertDatabaseCount('compra_detalle_consolidado', 2);
        $this->assertDatabaseCount('movimientos', 2);
        $this->assertSame(11, $this->saldo('P-001'));
        $this->get($url)->assertOk()->assertSee('PROVEEDOR B')->assertSee('Requerimiento cubierto')
            ->assertViewHas('lineas', fn ($filas) => $filas->sole()['comprada'] === 20 && $filas->sole()['pendiente'] === 0);
        $this->estado($consolidado, 'cerrar');
        $this->post($ruta, $otra)->assertSessionHasNoErrors();
        $this->get(route('flujo.compras.index', ['estado' => 'cerrado']))->assertOk()->assertSee('Dotación de noviembre');
        $this->get(route('flujo.consolidados.show', $consolidado))->assertOk()->assertSee('Ver seguimiento de compra');
        $this->assertDatabaseCount('movimientos', 2);
    }

    public function test_una_factura_con_varios_productos_distribuye_por_linea_y_respeta_ambos_limites(): void
    {
        $a = $this->aprobado('Equipo A', 5);
        $b = $this->aprobado('Equipo B', 8);
        $a->lineas()->create(['producto_codigo' => 'P-002', 'original' => 3, 'cantidad' => 3]);
        $arnes = bin2hex('P-001');
        $casco = bin2hex('P-002');
        $ruta = route('operador.compras.store');
        $base = $this->datosCompra('F-20', ['P-001', 'P-002'], [10, 4], ['consolidados' => [$a->id, $b->id]]);
        $this->actingAs($this->central)->postJson($ruta, $base)->assertUnprocessable()->assertJsonValidationErrors('distribucion');
        foreach ([
            [$a->id => [$arnes => 5], $b->id => [$arnes => 6]],
            [$a->id => [$arnes => 6], $b->id => [$arnes => 1]],
            [$a->id => [$arnes => 4], $b->id => [$casco => 2]],
        ] as $invalida) {
            $this->postJson($ruta, $base + ['distribucion' => $invalida])->assertUnprocessable()->assertJsonValidationErrors('distribucion');
        }
        $this->postJson($ruta, [...$base, 'producto_codigo' => ['P-001', 'P-001']])
            ->assertUnprocessable()->assertJsonValidationErrors('producto_codigo.1');
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('movimientos', 0);
        $this->post($ruta, $base + ['distribucion' => [$a->id => [$arnes => 4, $casco => 3], $b->id => [$arnes => 6]]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('compra_detalle_consolidado', 3);
        $this->assertDatabaseCount('compra_detalles', 2);
        $this->assertDatabaseCount('movimientos', 2);
        $this->assertSame(10, $this->saldo('P-001'));
        $this->assertSame(4, $this->saldo('P-002'));

        $this->get(route('flujo.compras.index'))->assertOk()->assertViewHas('lineas', function ($filas) use ($a, $b) {
            $arneses = $filas->where('codigo', 'P-001')->keyBy('consolidado_id');

            return $arneses[$a->id]['comprada'] === 4 && $arneses[$a->id]['sugerida'] === 1
                && $arneses[$b->id]['comprada'] === 6 && $arneses[$b->id]['sugerida'] === 2;
        });
        $this->get(route('operador.compras.show', DB::table('compras')->sole()->id))->assertOk()
            ->assertSee('P-001 · 4 unidades')->assertSee('P-001 · 6 unidades')->assertSee('P-002 · 3 unidades');
    }

    public function test_factura_historica_ambigua_pendiente_se_concilia_sin_movimientos_y_sin_perder_vinculos(): void
    {
        $a = $this->aprobado('Histórico A', 5);
        $b = $this->aprobado('Histórico B', 7);
        $ruta = route('operador.compras.store');
        $this->actingAs($this->central)->post($ruta, $this->datosCompra('F-HIST', ['P-001'], [8]))->assertSessionHasNoErrors();
        $factura = DB::table('compras')->sole();
        $detalle = DB::table('compra_detalles')->sole();
        DB::table('compra_consolidado')->insert([
            ['compra_id' => $factura->id, 'consolidado_id' => $a->id],
            ['compra_id' => $factura->id, 'consolidado_id' => $b->id],
        ]);
        $historica = ConstanciaGerencia::create([
            'user_id' => $this->central->id, 'medio' => 'Correo', 'fecha' => today(), 'autorizo' => 'Gerencia',
            'referencia' => 'Autorización anterior', 'plan' => [['producto' => 'Arnés QA', 'propuesta' => 8]],
        ]);
        $historica->consolidados()->attach([$a->id, $b->id]);
        $this->get(route('flujo.compras.index'))->assertOk()->assertSee('Pendiente de conciliación')
            ->assertSee('planificación sin desglose confirmado')
            ->assertViewHas('lineas', fn ($filas) => $filas->sum('comprada') === 0 && $filas->whereNull('decidida')->count() === 2);
        $this->get(route('operador.compras.show', $factura->id))->assertOk()->assertSee('Confirmar conciliación');
        $this->postJson(route('flujo.compras.constancia'), $this->datosConstancia($b, [
            $b->lineas()->sole()->id => ['existencia' => 7, 'compra' => 0],
        ]))->assertUnprocessable()->assertJsonValidationErrors('propuestas');
        $siguiente = $this->datosCompra('F-NUEVA', ['P-001'], [2], [
            'consolidados' => [$a->id], 'distribucion' => [$a->id => [bin2hex('P-001') => 2]],
        ]);
        $this->postJson($ruta, $siguiente)->assertUnprocessable()->assertJsonValidationErrors('distribucion');
        $conciliar = route('operador.compras.conciliar', $factura->id);
        $this->actingAs($this->regional)->postJson($conciliar, ['confirmar' => 1])->assertForbidden();
        $this->actingAs($this->central)->postJson($conciliar, ['confirmar' => 1, 'distribucion' => [
            $a->id => [$detalle->id => 5], $b->id => [$detalle->id => 4],
        ]])->assertUnprocessable()->assertJsonValidationErrors('distribucion');
        $datos = ['confirmar' => 1, 'distribucion' => [$a->id => [$detalle->id => 3], $b->id => [$detalle->id => 4]]];
        $this->post($conciliar, $datos)->assertSessionHasNoErrors();
        $this->post($conciliar, ['confirmar' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('movimientos', 1);
        $this->assertDatabaseCount('compra_consolidado', 2);
        $this->assertDatabaseCount('compra_detalle_consolidado', 2);
        $this->assertSame(8, $this->saldo('P-001'));
        $this->post($ruta, $siguiente)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('movimientos', 2);
        $this->assertSame(10, $this->saldo('P-001'));
        $this->get(route('flujo.compras.index'))->assertOk()->assertDontSee('Pendiente de conciliación')
            ->assertViewHas('lineas', fn ($filas) => $filas->sum('comprada') === 9);
    }

    public function test_plan_distribuye_el_saldo_entre_consolidados_y_un_plan_incompleto_no_permite_cerrar(): void
    {
        $a = $this->aprobado('Primero', 5);
        $b = $this->aprobado('Segundo', 3);
        $idA = $a->lineas()->sole()->id;
        $idB = $b->lineas()->sole()->id;
        $this->stock('P-001', 4);
        $ruta = route('flujo.compras.constancia');

        $this->actingAs($this->central)->post($ruta, $this->datosConstancia($a, [
            $idA => ['existencia' => 4, 'compra' => 0],
        ]))->assertSessionHasNoErrors();
        $this->postJson($ruta, $this->datosConstancia($b, [
            $idB => ['existencia' => 1, 'compra' => 2],
        ]))->assertUnprocessable()->assertJsonValidationErrors('propuestas');
        $this->postJson(route('flujo.consolidados.transicion', [$a, 'cerrar']), [
            'version' => $a->eventos()->max('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors('estado');

        $this->get(route('flujo.compras.index'))->assertOk()
            ->assertViewHas('lineas', fn ($filas) => $filas->keyBy('id')[$idB]['sugerida'] === 3);
        $this->post($ruta, $this->datosConstancia($b, [
            $idB => ['existencia' => 0, 'compra' => 3],
        ]))->assertSessionHasNoErrors();
        $this->assertSame($b->id, ConstanciaGerencia::latest('id')->firstOrFail()->plan[0]['consolidado_id']);
        $this->assertSame(4, $this->saldo('P-001'));
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_resumen_totaliza_el_saldo_y_una_factura_compartida_una_sola_vez(): void
    {
        $a = $this->aprobado('Equipo norte', 8);
        $b = $this->aprobado('Equipo sur', 7);
        $this->stock('P-001', 4);
        $ruta = route('flujo.compras.index', ['consolidados' => [$a->id, $b->id]]);
        $this->actingAs($this->central)->get($ruta)->assertOk()->assertSee('Resumen por producto')
            ->assertDontSee('Registrar constancia')->assertViewHas('resumen', fn ($filas) =>
                $filas->sole()['central'] === 4 && $filas->sole()['compra_total'] === 11);
        $this->get(route('flujo.compras.index', ['seleccion_manual' => 1]))->assertOk()
            ->assertViewHas('resumen', fn ($filas) => $filas->isEmpty());

        $datos = $this->datosCompra('REPARTO-06', ['P-001'], [6], [
            'consolidados' => [$a->id, $b->id],
            'distribucion' => [$a->id => [bin2hex('P-001') => 2], $b->id => [bin2hex('P-001') => 4]],
        ]);
        $this->postJson(route('operador.compras.store'), [...$datos, 'autorizado_por' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('autorizado_por');
        $this->assertDatabaseCount('movimientos', 0);
        $this->post(route('operador.compras.store'), $datos)->assertSessionHasNoErrors();

        $this->get($ruta)->assertOk()->assertViewHas('resumen', function ($filas) {
            $fila = $filas->sole();

            return $fila['aprobada'] === 15 && $fila['central'] === 10 && $fila['compra_total'] === 11
                && $fila['comprada'] === 6 && $fila['pendiente'] === 5
                && $fila['facturas']->count() === 1 && (int) $fila['facturas']->first()['cantidad'] === 6;
        });
        $this->get(route('flujo.compras.index', ['consolidados' => [$a->id]]))->assertOk()
            ->assertViewHas('resumen', fn ($filas) => $filas->sole()['comprada'] === 2
                && (int) $filas->sole()['facturas']->sole()['cantidad'] === 2);
        $this->get(route('operador.compras.show', DB::table('compras')->sole()->id))
            ->assertOk()->assertSee('Autorizado por')->assertSee('Gerencia QA');
        $this->assertDatabaseHas('compras', ['no_factura' => 'REPARTO-06', 'autorizado_por' => 'Gerencia QA']);
        $this->assertDatabaseCount('constancias_gerencia', 0);
        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_plan_interno_cubre_con_existencias_y_cerrar_compras_no_entrega_productos(): void
    {
        $consolidado = $this->aprobado('Reposición de campo', 7);
        $linea = $consolidado->lineas()->sole();
        $this->stock('P-001', 7);
        $url = route('flujo.compras.plan');
        $datos = ['consolidados' => [$consolidado->id], 'propuestas' => [
            $linea->id => ['existencia' => 7, 'compra' => 0],
        ]];
        $this->actingAs($this->coordinador)->post($url, $datos)->assertForbidden();
        $this->actingAs($this->regional)->post($url, $datos)->assertForbidden();
        $this->actingAs($this->central)->post($url, $datos)->assertSessionHasNoErrors();
        $this->assertSame($datos['propuestas'], $consolidado->fresh()->plan_compra);
        $this->assertSame('aprobado', $consolidado->fresh()->estado);
        $this->assertSame(7, $this->saldo('P-001'));
        $this->assertDatabaseCount('constancias_gerencia', 0);

        $this->get(route('flujo.consolidados.show', $consolidado))->assertOk()->assertSee('Cerrar compras');
        $this->estado($consolidado, 'cerrar');
        $this->assertSame('cerrado', $consolidado->fresh()->estado);
        $this->get(route('flujo.consolidados.show', $consolidado))->assertOk()
            ->assertSee('Compras cerradas')->assertSee('Consulta las asignaciones para conocer el avance de las entregas');
        $this->assertDatabaseCount('asignaciones_inventarios', 0);
        $this->assertDatabaseCount('movimientos', 0);
        $this->assertSame(7, $this->saldo('P-001'));
    }

    public function test_excel_valida_filas_revierte_si_falta_stock_y_genera_hojas_por_colaborador(): void
    {
        $this->stock('P-001', 3);
        $this->stock('P-002', 2);
        $this->actingAs($this->regional)->get(route('flujo.importar.index'))->assertForbidden();
        $this->actingAs($this->central)->get(route('flujo.importar.index'))->assertOk();
        $plantilla = $this->get(route('flujo.importar.plantilla'))->assertOk()->assertDownload('plantilla-asignaciones.xlsx');
        $rutaPlantilla = tempnam(sys_get_temp_dir(), 'edson-plantilla-');
        try {
            file_put_contents($rutaPlantilla, $plantilla->streamedContent());
            $libro = IOFactory::createReader('Xlsx')->load($rutaPlantilla);
            $this->assertSame('CodigosMotivo', $libro->getSheetByName('Asignaciones')->getCell('G2')->getDataValidation()->getFormula1());
            $this->assertSame('robo_injustificado', $libro->getSheetByName('Motivos')->getCell('A4')->getValue());
            $libro->disconnectWorksheets();
        } finally {
            unlink($rutaPlantilla);
        }
        $dia = today()->toDateString();
        $filas = [
            ['COL-001', 'P-001', 1, $dia, 'Gerencia', 'Correo', 'desgaste', ''],
            ['COL-001', 'P-002', 1, $dia, 'Gerencia', 'WhatsApp', '', ''],
            ['COL-002', 'P-001', 1, $dia, 'Gerencia', 'Correo', '', ''],
        ];
        $ruta = route('flujo.importar.preview');
        $this->post($ruta, ['excel' => $this->xlsx([$filas[0], $filas[0]])])
            ->assertUnprocessable()->assertSee('El colaborador y el código se repiten');
        $this->post($ruta, ['excel' => $this->xlsx([$filas[0], ['COL-002', 'NO-EXISTE', 2, $dia]])])
            ->assertUnprocessable()->assertSee('Código de producto inexistente');
        $this->assertDatabaseCount('asignaciones_inventarios', 0);
        $this->assertSame(3, $this->saldo('P-001'));

        foreach ([$filas, array_reverse($filas)] as $contenido) {
            $this->post($ruta, ['excel' => $this->xlsx($contenido)])->assertSessionHasNoErrors()->assertRedirect();
        }
        [$primero, $duplicado] = ImportacionAsignacion::get()->values()->all();
        $this->get(route('flujo.importar.show', $primero))->assertOk()->assertSee('Confirmar importación');
        $this->assertSame(3, $this->saldo('P-001'));
        $confirmar = route('flujo.importar.confirmar', $primero);
        $this->post($confirmar)->assertSessionHasNoErrors();
        $this->post($confirmar)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('asignaciones_inventarios', 3);
        $this->assertDatabaseCount('entregas_inventario', 2);
        $this->assertSame(1, $this->saldo('P-001'));
        $this->assertSame(1, $this->saldo('P-002'));
        $this->assertDatabaseMissing('asignaciones_inventarios', ['bodega_id' => $this->regional->bodega_id]);
        $entregas = collect($primero->refresh()->entregas);
        $this->assertSame(2, $entregas->firstWhere('codigo', 'COL-001')['productos']);
        $this->get(route('operador.asignaciones.pdf', $entregas->firstWhere('codigo', 'COL-001')['grupo']))->assertOk()
            ->assertViewHas('asignaciones', fn ($filas) => $filas->count() === 2);
        $descarga = $this->get(route('flujo.importar.hojas', $primero))->assertOk()->assertDownload('hojas-asignacion.zip');
        $rutaZip = $descarga->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($rutaZip));
        $this->assertSame(3, $zip->numFiles);
        $this->assertStringContainsString('Técnica norte', $zip->getFromName('001-col-001.html'));
        $this->assertStringContainsString('src="logo.png"', $zip->getFromName('001-col-001.html'));
        $zip->close();
        unlink($rutaZip);
        $this->postJson(route('flujo.importar.confirmar', $duplicado))->assertUnprocessable()->assertJsonValidationErrors('excel');
        $this->assertNull($duplicado->refresh()->confirmada_en);
        $this->assertDatabaseCount('asignaciones_inventarios', 3);
        $this->artisan('inventario:contrastar-saldos', ['--lotes' => true])->assertSuccessful();
    }

    public function test_la_vista_previa_detecta_saldo_sin_conciliar_sin_movimientos(): void
    {
        $this->stock('P-001', 3);
        DB::table('inventario_lotes_operativos')->where('producto_codigo', 'P-001')->update(['cantidad' => 2]);
        $this->actingAs($this->central)->post(route('flujo.importar.preview'), [
            'excel' => $this->xlsx([['COL-001', 'P-001', 1, today()->toDateString()]]),
        ])->assertUnprocessable()->assertSee('requiere conciliación');
        $this->assertSame(3, $this->saldo('P-001'));
        $this->assertDatabaseCount('asignaciones_inventarios', 0);
        $this->assertDatabaseCount('importaciones_asignacion', 0);
    }

    public function test_confirmar_revalida_stock_y_rechaza_lote_completo_si_cambio(): void
    {
        $this->stock('P-001', 1);
        $this->stock('P-002', 1);
        $this->actingAs($this->central)->post(route('flujo.importar.preview'), [
            'excel' => $this->xlsx([['COL-001', 'P-001', 1, today()->toDateString()], ['COL-001', 'P-002', 1, today()->toDateString()]]),
        ])->assertSessionHasNoErrors();
        $this->post(route('operador.asignaciones.store'), [
            'solicitud_id' => (string) Str::uuid(), 'colaborador_codigo' => 'COL-002',
            'items' => [['producto_codigo' => 'P-002', 'bodega_id' => $this->bodega->id]],
        ])->assertSessionHasNoErrors();
        $lote = ImportacionAsignacion::sole();
        $this->postJson(route('flujo.importar.confirmar', $lote))->assertUnprocessable()->assertJsonValidationErrors('fila_3');
        $this->assertNull($lote->refresh()->confirmada_en);
        $this->assertDatabaseCount('asignaciones_inventarios', 1);
        $this->assertSame(1, $this->saldo('P-001'));
    }

    #[DataProvider('motivos')]
    public function test_motivo_manual_conserva_custodia_y_solo_robo_injustificado_calcula_referencia(string $codigo, ?string $referencia): void
    {
        $this->stock('P-001', 2, 60);
        $this->travelTo(now()->startOfDay()->addHours(8));
        $item = ['producto_codigo' => 'P-001', 'bodega_id' => $this->bodega->id];
        $base = ['colaborador_codigo' => 'COL-001'];
        $ruta = route('operador.asignaciones.store');
        $this->actingAs($this->central)->post($ruta, $base + ['solicitud_id' => (string) Str::uuid(),
            'fecha' => now()->subDays(30)->toDateTimeString(), 'items' => [$item]])->assertSessionHasNoErrors();
        $this->post($ruta, $base + ['solicitud_id' => (string) Str::uuid(),
            'items' => [$item + ['motivo_asignacion' => $codigo]]])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->saldo('P-001'));
        $this->assertCount(1, AsignacionInventario::custodiaActiva()->get());
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        if ($codigo === 'desgaste') {
            $this->assertDatabaseCount('alertas_reemplazos_rrhh', 0);
        } else {
            $alerta = AlertaReemplazo::sole();
            $this->assertSame($referencia, $alerta->referencia_proporcional);
            $this->assertFalse($alerta->descuento_aplicable);
        }
        $this->travelBack();
    }

    public function test_robo_injustificado_sin_custodia_anterior_genera_revision_sin_importe(): void
    {
        $this->stock('P-001', 1);
        $this->actingAs($this->central)->post(route('operador.asignaciones.store'), [
            'solicitud_id' => (string) Str::uuid(), 'colaborador_codigo' => 'COL-001',
            'items' => [['producto_codigo' => 'P-001', 'bodega_id' => $this->bodega->id,
                'motivo_asignacion' => 'robo_injustificado']],
        ])->assertSessionHasNoErrors();
        $alerta = AlertaReemplazo::sole();
        $this->assertNull($alerta->asignacion_anterior_id);
        $this->assertNull($alerta->fecha_asignacion_anterior);
        $this->assertNull($alerta->referencia_proporcional);
        $this->assertSame('Robo injustificado', $alerta->motivo_etiqueta);
    }

    public static function motivos(): array
    {
        return [
            'desgaste' => ['desgaste', null], 'robo' => ['robo', null],
            'robo injustificado' => ['robo_injustificado', '30.00'],
        ];
    }

    private function datosCompra(string $factura, array $productos, array $cantidades, array $extra = []): array
    {
        return $extra + [
            'fecha_compra' => today()->toDateString(), 'no_factura' => $factura, 'proveedor_tipo' => 'nuevo',
            'proveedor_nombre' => 'Proveedor A', 'autorizado_por' => 'Gerencia QA', 'forma_pago' => 'Al contado', 'producto_tipo' => array_fill(0, count($productos), 'existente'),
            'producto_codigo' => $productos, 'cantidad' => $cantidades, 'precio_unitario' => array_fill(0, count($productos), '12.00'),
        ];
    }

    private function datosConstancia(Consolidado $consolidado, array $propuestas): array
    {
        return [
            'consolidados' => [$consolidado->id], 'medio' => 'Correo', 'fecha' => today()->toDateString(),
            'autorizo' => 'Gerencia QA', 'referencia' => 'Correo QA', 'propuestas' => $propuestas,
        ];
    }

    private function datosInspeccion(int $cantidad = 1): array
    {
        return [
            'solicitud_id' => (string) Str::uuid(), 'fecha' => today()->toDateString(), 'region_id' => $this->norte->id,
            'colaborador_codigo' => 'COL-001', 'cantidades' => [$this->articulo => $cantidad],
        ];
    }

    private function aprobado(string $nombre, int $cantidad): Consolidado
    {
        $fila = Consolidado::create(['nombre' => $nombre, 'coordinador_id' => $this->coordinador->id, 'operador_id' => $this->central->id,
            'desde' => today(), 'hasta' => today(), 'estado' => 'aprobado', 'aprobado_por' => $this->coordinador->id, 'aprobado_en' => now()]);
        $fila->lineas()->create(['producto_codigo' => 'P-001', 'original' => $cantidad, 'cantidad' => $cantidad]);
        $fila->eventos()->create(['accion' => 'aprobar', 'cambios' => [], 'user_id' => $this->coordinador->id]);

        return $fila;
    }

    private function estado(Consolidado $consolidado, string $accion, array $datos = []): void
    {
        $this->post(route('flujo.consolidados.transicion', [$consolidado, $accion]), [
            'version' => $consolidado->eventos()->max('id'), ...$datos,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function stock(string $codigo, int $cantidad, ?float $valor = null): void
    {
        DB::transaction(fn () => app(InventarioStockService::class)->incrementar($this->bodega->id, $codigo, $cantidad, $valor));
    }

    private function saldo(string $codigo): int
    {
        return (int) DB::table('inventarios')->where('bodega_id', $this->bodega->id)->where('producto_codigo', $codigo)->sum('cantidad');
    }

    private function xlsx(array $filas): UploadedFile
    {
        $archivo = tempnam(sys_get_temp_dir(), 'edson-excel-');
        try {
            app(ExcelAsignaciones::class)->plantilla()->save($archivo);
            $libro = IOFactory::createReader('Xlsx')->load($archivo);
            $libro->getActiveSheet()->fromArray($filas, startCell: 'A2');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($archivo);
            $libro->disconnectWorksheets();

            return UploadedFile::fake()->createWithContent('asignaciones.xlsx', file_get_contents($archivo));
        } finally {
            unlink($archivo);
        }
    }
}
