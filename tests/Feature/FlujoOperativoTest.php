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
        $this->actingAs($this->admin)->get(route('flujo.catalogos'))->assertOk()->assertSee('Cinta reflectiva')->assertSee('Robo injustificado');
        $this->post(route('flujo.catalogos.producto'), ['codigo' => 'P-NUEVO', 'nombre' => 'Equipo de inspección'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['codigo' => 'P-NUEVO']);
        $this->assertDatabaseCount('inventarios', 0);
        $this->post(route('flujo.catalogos.region'), ['nombre' => 'Centro', 'activa' => 1])->assertSessionHasNoErrors();
        $this->post(route('flujo.catalogos.articulo'), [
            'seccion' => 'comun', 'producto_codigo' => 'P-002', 'etiqueta' => 'Casco', 'descripcion' => 'De seguridad', 'maximo' => 12, 'orden' => 1, 'activo' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inspeccion_articulos', ['producto_codigo' => 'P-002', 'maximo' => 12]);
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
        $this->postJson(route('flujo.inspecciones.store'), [...$this->datosInspeccion(), 'cantidades' => [$this->articulo => 21]])
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
            ->assertViewHas('lineas', fn ($filas) => $filas->sole()['sugerida'] === 5);
        $this->post(route('flujo.compras.constancia'), [
            'consolidados' => [$a->id, $b->id], 'medio' => 'Correo', 'fecha' => today()->toDateString(), 'autorizo' => 'Gerencia QA',
            'referencia' => 'Correo AF-01', 'propuestas' => [bin2hex('P-001') => 6],
            'documento' => UploadedFile::fake()->create('autorizacion.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $constancia = ConstanciaGerencia::sole();
        $this->assertEquals(6, $constancia->plan[0]['propuesta']);
        $this->assertEquals(2, $constancia->consolidados()->count());
        $this->assertSame(2, $this->saldo('P-001'));
        $url = route('flujo.compras.documento', $constancia);
        $this->get($url)->assertOk();
        $this->actingAs($this->coordinador)->get($url)->assertForbidden();

        $factura = [
            'fecha_compra' => today()->toDateString(), 'no_factura' => 'QA-100', 'proveedor_tipo' => 'nuevo',
            'proveedor_nombre' => 'Proveedor flujo', 'forma_pago' => 'Al contado', 'producto_tipo' => ['existente'],
            'producto_codigo' => ['P-001'], 'cantidad' => [5], 'precio_unitario' => ['12.00'],
            'consolidados' => [$a->id, $b->id],
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
