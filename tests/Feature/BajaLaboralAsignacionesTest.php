<?php

namespace Tests\Feature;

use App\Models\AsignacionInventario;
use App\Models\AsignacionMovimiento;
use App\Models\BajaLaboral;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class BajaLaboralAsignacionesTest extends TestCase
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

    public static function motivosBaja(): array
    {
        return [['renuncia'], ['despido']];
    }

    #[DataProvider('motivosBaja')]
    public function test_baja_registrada_separa_la_recepcion_fisica_de_un_faltante(string $motivo): void
    {
        $this->actingAs($this->operador)->post(route('operador.asignaciones.store'), $this->salida(['CASCO', 'GUANTES']))
            ->assertSessionHasNoErrors();
        $entrega = EntregaInventario::sole();
        [$casco, $guantes] = $entrega->asignaciones()->orderBy('id')->get();
        $ruta = route('operador.asignaciones.devolver_lote');
        $formulario = route('operador.asignaciones.baja', 'EMP-01');
        $datos = [
            'solicitud_id' => (string) Str::uuid(),
            'seleccionadas' => [$casco->id, $guantes->id],
            'devoluciones' => [$casco->id => 1, $guantes->id => 1],
            'resoluciones' => [
                $casco->id => ['estado_devolucion' => 'buen_estado'],
                $guantes->id => ['estado_devolucion' => 'otra', 'resultado_otra' => 'sin_retorno', 'detalle_devolucion' => 'Equipo sin recuperar.'],
            ],
        ];
        $this->get($formulario)->assertNotFound();
        $this->postJson($ruta, $datos + ['baja_laboral_id' => 1, 'motivo_administrativo' => $motivo])->assertForbidden();
        $registrar = route('rrhh.colaboradores.baja.store', 'EMP-01');
        $personal = ['motivo' => $motivo, 'fecha_efectiva' => today()->toDateString(), 'observaciones' => 'Acta de personal'];
        $this->postJson($registrar, $personal)->assertForbidden();
        $this->actingAs($this->rrhh)->patch(route('rrhh.colaboradores.estado', 'EMP-01'))->assertSessionHasNoErrors();
        $this->actingAs($this->operador)->postJson($ruta, $datos + ['baja_laboral_id' => 1])->assertForbidden();
        $this->actingAs($this->rrhh)->post($registrar, $personal)->assertSessionHasNoErrors();
        $baja = BajaLaboral::sole();
        $this->assertSame($this->rrhh->id, $baja->registrada_por);
        $this->assertSame($motivo, $baja->motivo);
        $this->postJson($registrar, $personal)->assertUnprocessable();
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        $this->assertDatabaseCount('incidencias_inventario', 0);

        $datos['baja_laboral_id'] = $baja->id;
        $this->actingAs($this->operador)->get($formulario)->assertOk()->assertSee('Guantes de seguridad');
        $this->postJson($ruta, $datos + ['motivo_administrativo' => $motivo === 'renuncia' ? 'despido' : 'renuncia'])->assertUnprocessable();
        $this->post($ruta, $datos)->assertRedirect($formulario)->assertSessionHas('grupo_devolucion');
        $this->postJson($ruta, $datos)->assertUnprocessable()->assertJsonValidationErrors('solicitud_id');
        $this->assertDatabaseCount('asignacion_movimientos', 3);
        $this->assertDatabaseHas('asignacion_movimientos', ['baja_laboral_id' => $baja->id, 'motivo_administrativo' => $motivo,
            'user_id' => $this->operador->id, 'asignacion_inventario_id' => $casco->id, 'tipo' => 'Devolucion']);
        $this->assertDatabaseHas('incidencias_inventario', ['baja_laboral_id' => $baja->id, 'asignacion_id' => $guantes->id, 'resultado_stock' => 'sin_retorno']);
        $this->assertDatabaseHas('inventarios', ['producto_codigo' => 'CASCO', 'stock_tipo' => 'usado', 'cantidad' => 1]);
        $this->assertDatabaseMissing('inventarios', ['producto_codigo' => 'GUANTES', 'stock_tipo' => 'usado']);
        $this->get($formulario)->assertOk()->assertSee('No quedan artículos pendientes');
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
