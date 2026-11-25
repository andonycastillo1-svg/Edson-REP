<?php

namespace Tests\Feature;

use App\Models\AsignacionInventario;
use App\Models\AsignacionMovimiento;
use App\Models\AsignacionInventarioArchivo;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\EntregaInventario;
use App\Models\Producto;
use App\Models\Region;
use App\Models\User;
use App\Services\InventarioStockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class DevolucionCompletaAsignacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $rrhh;
    private User $operador;
    private Bodega $bodega;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('documentos');
        config(['inventario.seguimiento_unidades' => false]);
        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Administrador'], ['id' => 2, 'nombre' => 'Almacenista'],
            ['id' => 3, 'nombre' => 'Supervisor'], ['id' => 4, 'nombre' => 'Recursos Humanos'],
        ]);
        $this->bodega = Bodega::create(['nombre' => 'Bodega Norte', 'tipo' => 'Regional']);
        Bodega::create(['nombre' => 'Bodega central', 'tipo' => 'Principal']);
        $this->operador = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->bodega->id, 'name' => 'Responsable de bodega']);
        $this->rrhh = User::factory()->create(['role_id' => 4]);
        $region = Region::create(['nombre' => 'Región Norte', 'activa' => true]);
        Colaborador::create(['codigo' => 'EMP-01', 'nombre' => 'Andrea de Prueba', 'puesto' => 'Técnica',
            'estado' => 'Activo', 'region_id' => $region->id]);
        foreach (['CASCO' => 'Casco', 'GUANTES' => 'Guantes de seguridad'] as $codigo => $nombre) {
            Producto::create(['codigo' => $codigo, 'nombre' => $nombre, 'vida_util_meses' => 2]);
            DB::transaction(fn () => app(InventarioStockService::class)->incrementar($this->bodega->id, $codigo, 4));
        }
    }

    public function test_rrhh_resuelve_toda_la_custodia_y_archiva_una_hoja_incluso_con_faltantes(): void
    {
        $this->actingAs($this->operador)->post(route('operador.asignaciones.store'), $this->salida(['CASCO', 'GUANTES']))
            ->assertSessionHasNoErrors();
        [$casco, $guantes] = AsignacionInventario::orderBy('id')->get();
        $segundaBodega = Bodega::where('tipo', 'Principal')->sole();
        $legado = AsignacionInventario::create([
            'grupo_asignacion' => (string) Str::uuid(), 'producto_codigo' => 'GUANTES', 'colaborador_codigo' => 'EMP-01',
            'bodega_id' => $segundaBodega->id, 'user_id' => $this->operador->id, 'fecha' => today()->subMonth(),
            'cantidad_asignada' => 3, 'estado' => 'Activa',
        ]);
        $ruta = route('devoluciones.registrar', 'EMP-01');
        $formulario = route('devoluciones.formulario', 'EMP-01');
        $expediente = route('rrhh.colaboradores.expediente', 'EMP-01');
        $datos = ['solicitud_id' => (string) Str::uuid(), 'articulos' => [
            $casco->id => ['buen_estado' => 1, 'danado' => 0, 'sin_retorno' => 0],
            $guantes->id => ['buen_estado' => 0, 'danado' => 0, 'sin_retorno' => 1],
            $legado->id => ['buen_estado' => 1, 'danado' => 1, 'sin_retorno' => 1],
        ]];

        $this->actingAs($this->rrhh)->get($expediente)->assertOk()->assertDontSeeText('Registrar devolución');
        $this->get($formulario)->assertForbidden();
        $this->postJson($ruta, $datos)->assertForbidden();
        DB::table('sistema_permisos_usuario')->insert(['user_id' => $this->rrhh->id, 'permiso' => 'asignaciones.devolver', 'permitido' => true]);
        $this->rrhh->regionesOperativas()->attach(Colaborador::findOrFail('EMP-01')->region_id);
        DB::table('bodega_usuario_operativa')->insert(['user_id' => $this->rrhh->id, 'bodega_id' => $this->bodega->id, 'opera_inventario' => true]);
        $this->actingAs($this->rrhh->fresh())->postJson($ruta, $datos)->assertForbidden();
        $this->get($formulario)->assertForbidden();
        DB::table('bodega_usuario_operativa')->insert(['user_id' => $this->rrhh->id, 'bodega_id' => $segundaBodega->id, 'opera_inventario' => true]);

        $this->actingAs($this->rrhh->fresh())->get($expediente)->assertOk()->assertSeeText('Registrar devolución');
        $this->get($formulario)->assertOk()->assertSeeText('Guantes de seguridad')->assertSeeText('Bodega central')
            ->assertSeeText('Región Norte')->assertSeeText($this->rrhh->name);
        $this->postJson($ruta, ['solicitud_id' => $datos['solicitud_id'], 'articulos' => [$casco->id => $datos['articulos'][$casco->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('articulos.'.$guantes->id);
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        $this->assertDatabaseCount('asignacion_inventario_archivos', 0);
        $this->get(route('operador.compras.create'))->assertForbidden();

        $respuesta = $this->post($ruta, $datos)->assertRedirect();
        $documento = AsignacionInventarioArchivo::sole();
        $respuesta->assertRedirect(route('devoluciones.pdf', $documento->grupo_devolucion));
        $this->postJson($ruta, $datos)->assertUnprocessable()->assertJsonValidationErrors('solicitud_id');
        $this->postJson($ruta, [...$datos, 'solicitud_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('articulos');
        $this->assertDatabaseCount('asignacion_movimientos', 7);
        $this->assertDatabaseHas('asignacion_movimientos', ['motivo_administrativo' => 'custodia_total',
            'user_id' => $this->rrhh->id, 'asignacion_inventario_id' => $guantes->id, 'estado_devolucion' => 'sin_retorno',
            'bodega_retorno_id' => null, 'stock_tipo_resultante' => null]);
        $this->assertDatabaseHas('incidencias_inventario', ['asignacion_id' => $guantes->id, 'resultado_stock' => 'sin_retorno']);
        $this->assertDatabaseHas('inventarios', ['producto_codigo' => 'CASCO', 'bodega_id' => $this->bodega->id, 'stock_tipo' => 'usado', 'cantidad' => 1]);
        $this->assertDatabaseHas('inventarios', ['producto_codigo' => 'GUANTES', 'bodega_id' => $segundaBodega->id, 'stock_tipo' => 'danado', 'cantidad' => 1]);
        $this->assertDatabaseHas('inventarios', ['producto_codigo' => 'GUANTES', 'bodega_id' => $segundaBodega->id, 'stock_tipo' => 'usado', 'cantidad' => 1]);
        $this->assertDatabaseMissing('inventarios', ['producto_codigo' => 'GUANTES', 'bodega_id' => $this->bodega->id, 'stock_tipo' => 'usado']);
        $this->assertFalse(AsignacionInventario::custodiaAbierta()->exists());
        $this->assertDatabaseHas('colaboradores', ['codigo' => 'EMP-01', 'estado' => 'Activo']);
        $this->get($expediente.'?seccion=historial')->assertOk()->assertSeeText('Descargar PDF');
        $this->get(route('devoluciones.pdf', $documento->grupo_devolucion))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', Storage::disk('documentos')->get($documento->ruta));
        $ajeno = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->bodega->id]);
        $this->actingAs($ajeno)->get(route('devoluciones.pdf', $documento->grupo_devolucion))->assertForbidden();
    }

    public function test_fallo_al_guardar_pdf_revierte_toda_la_devolucion(): void
    {
        $this->actingAs($this->operador)->post(route('operador.asignaciones.store'), $this->salida(['CASCO']))
            ->assertSessionHasNoErrors();
        $fila = AsignacionInventario::sole();
        $disco = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disco->shouldReceive('put')->once()->andReturn(false);
        $disco->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('documentos')->andReturn($disco);
        $solicitud = (string) Str::uuid();

        $this->postJson(route('devoluciones.registrar', 'EMP-01'), [
            'solicitud_id' => $solicitud,
            'articulos' => [$fila->id => ['buen_estado' => 1, 'danado' => 0, 'sin_retorno' => 0]],
        ])->assertServerError();
        $this->assertSame(1, $fila->fresh()->cantidad_asignada);
        $this->assertDatabaseCount('asignacion_movimientos', 1);
        $this->assertDatabaseCount('asignacion_inventario_archivos', 0);
        $this->assertDatabaseMissing('inventarios', ['producto_codigo' => 'CASCO', 'stock_tipo' => 'usado']);
        $this->assertDatabaseMissing('solicitudes_inventario', ['clave' => $solicitud]);
    }

    public function test_listados_paginados_historial_expediente_y_evidencias_respetan_los_filtros(): void
    {
        $operador = $this->operador;
        for ($i = 1; $i <= 12; $i++) {
            $codigo = 'QA-'.str_pad($i, 2, '0', STR_PAD_LEFT);
            Colaborador::create(['codigo' => $codigo, 'nombre' => 'Personal '.$codigo, 'puesto' => 'Técnico', 'estado' => 'Activo']);
            $entrega = EntregaInventario::create(['id' => (string) Str::uuid(), 'salida_en' => now(), 'modalidad' => 'directa']);
            $fila = AsignacionInventario::create([
                'grupo_asignacion' => $entrega->id, 'colaborador_codigo' => $codigo, 'producto_codigo' => 'CASCO',
                'bodega_id' => $this->bodega->id, 'cantidad_asignada' => 1, 'fecha' => today(), 'estado' => 'Activa', 'user_id' => $operador->id,
            ]);
            $fila->movimientos()->create(['tipo' => 'Asignacion', 'cantidad' => 1, 'user_id' => $operador->id, 'ocurrio_en' => now()]);
        }
        $this->actingAs($operador);
        $listado = route('operador.asignaciones.index');
        $this->get($listado.'?q=Personal')->assertOk()->assertViewHas('colaboradores', fn ($p) =>
            $p->total() === 12 && $p->count() === 10 && str_contains($p->nextPageUrl(), 'q=Personal'));
        $this->get($listado.'?q=Personal&page=2')->assertOk()->assertSee('Personal QA-12')->assertDontSee('Personal QA-01');
        $this->get($listado.'?q=Personal&seguimiento=documentacion')->assertOk()
            ->assertViewHas('entregas', fn ($p) => $p->total() === 12 && str_contains($p->nextPageUrl(), 'seguimiento=documentacion'));
        $fila = AsignacionInventario::where('colaborador_codigo', 'QA-01')->sole();
        $this->post(route('operador.asignaciones.documento_entrega', $fila->grupo_asignacion), [
            'pdf_firmado' => UploadedFile::fake()->create('firma-qa.pdf', 8, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->get($listado.'?q=Personal&seguimiento=documentacion')->assertOk()->assertViewHas('faltantes', 11);
        $this->get(route('operador.asignaciones.articulo', $fila))->assertOk()->assertSee('Hoja firmada registrada');
        $this->get(route('operador.asignaciones.entrega', $fila->grupo_asignacion))->assertOk()->assertSee('firma-qa.pdf');
        $tercero = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->bodega->id]);
        $this->actingAs($tercero)->get(route('operador.asignaciones.articulo', $fila))->assertForbidden();
        $this->actingAs($operador)->get(route('operador.asignaciones.historial', [
            'desde' => today()->toDateString(), 'hasta' => today()->toDateString(), 'producto' => 'Casco',
            'colaborador' => 'Personal', 'tipo' => 'Asignacion', 'bodega' => $this->bodega->id,
        ]))->assertOk()->assertViewHas('movimientos', fn ($p) =>
            $p->total() === 12 && $p->count() === 10 && str_contains($p->nextPageUrl(), 'producto=Casco'));
        $url = route('rrhh.colaboradores.expediente', 'EMP-01');
        $this->actingAs($this->rrhh)->get($url)->assertOk()->assertSee('Región Norte')->assertSee('Técnica')
            ->assertSee('Productos asignados')->assertSee('Descargar ficha Excel');
        $this->get($url.'?seccion=productos')->assertOk()->assertSee('Productos bajo custodia');
        $this->get($url.'?seccion=historial')->assertOk()->assertSee('Historial de productos');
        $this->get($url.'?seccion=incidencias')->assertOk()->assertSee('Incidencias registradas');
    }

    public function test_reemplazo_verifica_vida_y_registra_la_condicion_con_una_sola_alerta(): void
    {
        $ruta = route('operador.asignaciones.store');
        $this->actingAs($this->operador)->post($ruta, $this->salida(['CASCO'], ['fecha' => now()->subDays(80)->toDateTimeString()]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 0);
        $anterior = AsignacionInventario::sole();
        $datos = $this->salida(['CASCO']);
        $datos['items'][0]['sustituir_asignacion_id'] = $anterior->id;
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $nueva = AsignacionInventario::custodiaActiva()->sole();
        $this->assertSame($anterior->id, $nueva->asignacion_anterior_id);
        $this->assertSame('Vencimiento de vida útil', $nueva->motivo_registrado);
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 0);
        $datos = $this->salida(['CASCO']);
        $datos['items'][0]['sustituir_asignacion_id'] = $nueva->id;
        $this->postJson($ruta, $datos)->assertUnprocessable()->assertJsonValidationErrors('motivo_asignacion');
        $datos['items'][0]['motivo_asignacion'] = 'robo';
        $datos['items'][0]['condicion_anterior'] = ['estado_devolucion' => 'robado'];
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 1);
        $this->assertDatabaseCount('asignacion_movimientos', 3);
        $this->assertDatabaseHas('incidencias_inventario', ['resultado_stock' => 'sin_retorno', 'asignacion_id' => $nueva->id, 'registrado_por' => $this->operador->id]);
        $this->assertSame('Cerrada', $nueva->fresh()->estado);
        $this->assertNotNull($anterior->fresh()->sustituida_por_id);
        $this->assertDatabaseMissing('inventarios', ['producto_codigo' => 'CASCO', 'stock_tipo' => 'usado']);
        $this->postJson($ruta, $datos)->assertUnprocessable()->assertJsonValidationErrors('solicitud_id');
        $this->actingAs($this->rrhh)->get(route('rrhh.colaboradores.expediente', ['EMP-01', 'seccion' => 'incidencias']))
            ->assertOk()->assertSee('Robo')->assertSee('Pendiente de revisión');
    }

    private function salida(array $codigos, array $opciones = []): array
    {
        return $opciones + [
            'solicitud_id' => (string) Str::uuid(),
            'colaborador_codigo' => 'EMP-01',
            'items' => array_map(fn ($codigo) => ['producto_codigo' => $codigo, 'bodega_id' => $this->bodega->id], $codigos),
        ];
    }
}
