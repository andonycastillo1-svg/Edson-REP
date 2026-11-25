<?php

namespace Tests\Feature;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\EntregaInventario;
use App\Models\IncidenciaInventario;
use App\Models\InventarioLoteOperativo;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Services\InventarioStockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabase;
use Tests\Support\ConDevolucionesCompletas;
use Tests\TestCase;

class EntregaCustodiaTest extends TestCase
{
    use RefreshDatabase, ConDevolucionesCompletas;

    private Bodega $regional;
    private Bodega $central;
    private User $almacenista;
    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('documentos');
        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Administrador'], ['id' => 2, 'nombre' => 'Almacenista'],
            ['id' => 3, 'nombre' => 'Supervisor'], ['id' => 4, 'nombre' => 'Recursos Humanos'],
        ]);
        $this->central = Bodega::create(['nombre' => 'Central', 'tipo' => 'Principal']);
        $this->regional = Bodega::create(['nombre' => 'Norte', 'tipo' => 'Regional']);
        $this->almacenista = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->regional->id]);
        $this->rrhh = User::factory()->create(['role_id' => 4]);
        Colaborador::create(['codigo' => 'C01', 'nombre' => 'Persona destinataria', 'puesto' => 'Técnico', 'estado' => 'Activo']);
        Producto::create(['codigo' => 'P01', 'nombre' => 'Botas', 'vida_util_meses' => 2]);
        config(['inventario.seguimiento_unidades' => true]);
        $this->agregarStock($this->regional, 3);
        $this->actingAs($this->almacenista);
    }

    public function test_salida_directa_sin_decisiones_manuales_reintento_y_custodia_de_otra_bodega(): void
    {
        $datos = $this->datosSalida();
        unset($datos['fecha'], $datos['aprobado_por'], $datos['medio_solicitud'], $datos['items'][0]['cantidad_asignada']);
        $ruta = route('operador.asignaciones.store');
        $this->get(route('operador.asignaciones.create'))->assertOk()
            ->assertDontSee('Reemplazo por daño')->assertDontSee('Confirma la recepción')->assertDontSee('stock_tipo');
        $this->postJson($ruta, [...$datos, 'items' => [[...$datos['items'][0], 'cantidad_asignada' => 2]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.cantidad_asignada');
        $this->postJson($ruta, [...$datos, 'items' => [...$datos['items'], ...$datos['items']]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.producto_codigo');
        $this->crearSalida($datos);
        $primera = AsignacionInventario::sole();
        $this->assertSame('Activa', $primera->estado);
        $this->assertEquals($this->almacenista->id, $primera->entrega->confirmada_por);
        $this->assertNotNull($primera->fecha_vencimiento);
        $this->assertEquals(2, $this->saldo('nuevo'));
        $this->postJson($ruta, $datos)->assertUnprocessable()->assertJsonValidationErrors('solicitud_id');

        $this->agregarStock($this->central, 1);
        $otro = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->central->id]);
        $this->actingAs($otro)->get(route('operador.asignaciones.entrega', $primera->grupo_asignacion))->assertForbidden();
        $this->postJson($ruta, $datos)->assertForbidden();
        $otraBodega = $this->datosSalida();
        $otraBodega['items'][0]['bodega_id'] = $this->central->id;
        $this->crearSalida($otraBodega);
        $segunda = AsignacionInventario::latest('id')->firstOrFail();
        $this->assertNull($primera->refresh()->sustituida_por_id);
        $this->assertNull($segunda->asignacion_anterior_id);
        $this->assertSame('adicional', $segunda->tipo_entrega);
        $this->assertDatabaseCount('asignaciones_inventarios', 2);
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        $this->assertDatabaseHas('inventarios', ['bodega_id' => $this->central->id, 'cantidad' => 0]);
    }

    public function test_reemplazo_explicito_y_devolucion_completa_en_operaciones_independientes(): void
    {
        $salida = now()->subDays(12);
        $recibida = now()->subDays(10);
        $this->crearSalida($this->datosSalida([
            'fecha' => $salida->toDateTimeString(), 'confirmar_recepcion' => 1,
            'medio_confirmacion' => 'presencial', 'recibida_en' => $recibida->toDateTimeString(),
        ]));
        $anterior = AsignacionInventario::sole();
        $this->assertEquals($recibida->timestamp, $anterior->periodos()->sole()->inicio_uso_en->timestamp);
        $this->assertEquals($recibida->copy()->addDays(60)->timestamp, $anterior->fecha_vencimiento->timestamp);

        $this->crearSalida($this->reemplazar($anterior, 'robo'));
        $nueva = AsignacionInventario::custodiaActiva()->sole();
        $this->assertEquals($anterior->id, $nueva->asignacion_anterior_id);
        $this->assertSame('Activa', $anterior->refresh()->estado);
        $this->assertSame('Custodia anterior sin retorno confirmado', $anterior->custodia_etiqueta);
        $this->get(route('sistema.datos.lista', 'asignaciones'))->assertOk()->assertSee($anterior->custodia_etiqueta);
        $this->assertEquals(0, $this->saldo('usado'));
        $this->assertEquals(1, $this->saldo('nuevo'));
        $alerta = AlertaReemplazo::sole();
        $this->assertSame('motivo_asignacion', $alerta->motivo_revision);
        $this->assertEquals($nueva->id, $alerta->asignacion_nueva_id);
        $this->assertNull($alerta->incidencia_inventario_id);

        $this->actingAs($this->rrhh)->get(route('rrhh.alertas.show', $alerta))->assertOk()
            ->assertSee('Robo')->assertSee('Historial del producto');
        $this->post(route('rrhh.alertas.regularizacion', $alerta), ['nota' => 'Verificar el retorno de la unidad.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('alerta_rrhh_actuaciones', ['accion' => 'solicitar_regularizacion', 'usuario_id' => $this->rrhh->id]);
        $this->assertEquals(0, $this->saldo('usado'));
        $this->actingAs($this->almacenista);
        $devolucion = route('devoluciones.registrar', $anterior->colaborador_codigo);
        $datos = $this->datosDevolucion($anterior);
        $this->postJson($devolucion, $datos)->assertUnprocessable()->assertJsonValidationErrors('articulos.'.$nueva->id);
        $datos['articulos'] += $this->datosDevolucion($nueva, 'sin_retorno')['articulos'];
        $this->post($devolucion, $datos)->assertSessionHasNoErrors();
        $this->assertSame('Devuelta', $anterior->refresh()->estado);
        $this->assertSame('Cerrada', $nueva->refresh()->estado);
        $this->assertEquals(1, $this->saldo('usado'));
        $this->assertGreaterThan(49 * 86400, $anterior->periodos()->sole()->vida_util_restante_segundos);
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 1);
        $movimiento = $anterior->movimientos()->where('tipo', 'Devolucion')->sole();
        $this->get(route('operador.asignaciones.hoja_devolucion', $movimiento->grupo_devolucion))->assertOk();
        $this->artisan('inventario:contrastar-saldos')->assertSuccessful();
    }

    #[DataProvider('condiciones')]
    public function test_condiciones_separan_devolucion_fisica_de_revision(string $motivo, ?int $dias, string $vida, bool $alerta, bool $retorno): void
    {
        $recibida = $dias === null ? null : now()->subDays($dias);
        $this->crearSalida($this->datosSalida([
            'fecha' => ($recibida ?? now())->copy()->subDay()->toDateTimeString(),
            'confirmar_recepcion' => 1, 'medio_confirmacion' => 'presencial',
            'recibida_en' => $recibida?->toDateTimeString(),
        ]));
        $asignacion = AsignacionInventario::sole();
        $this->assertSame('Activa', $asignacion->estado);
        $ruta = route('devoluciones.registrar', $asignacion->colaborador_codigo);
        $datos = $this->datosDevolucion($asignacion, $motivo);
        $this->postJson($ruta, ['solicitud_id' => $datos['solicitud_id']])->assertUnprocessable()->assertJsonValidationErrors('articulos');
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $incidencia = IncidenciaInventario::sole();
        $this->assertSame($retorno ? $motivo : 'otra', $incidencia->motivo);
        $this->assertSame($vida, $incidencia->estado_vida);
        $this->assertEquals($retorno ? 1 : 0, $this->saldo('danado'));
        $this->assertEquals(2, $this->saldo('nuevo'));
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', $alerta ? 1 : 0);
        $this->assertSame($retorno ? 'Dañada' : 'Cerrada', $asignacion->refresh()->estado);
        if (!$retorno) {
            $this->assertNull($asignacion->periodos()->sole()->bodega_retorno_id);
            $this->assertFalse(DB::table('inventarios')->whereIn('stock_tipo', ['perdido', 'robado'])->exists());
        }
    }

    public static function condiciones(): array
    {
        return [
            'daño vigente' => ['danado', 5, 'vigente', true, true],
            'daño vencido' => ['danado', 70, 'vencida', false, true],
            'daño desconocido' => ['danado', null, 'desconocida', true, true],
            'sin retorno con vida vencida' => ['sin_retorno', 70, 'vencida', false, false],
            'sin retorno con vida desconocida' => ['sin_retorno', null, 'desconocida', false, false],
        ];
    }

    public function test_tercero_confirmacion_posterior_pdf_privado_versionado_y_fotografia_opcional(): void
    {
        $datos = $this->datosSalida(['modalidad' => 'tercero', 'fecha' => now()->subDays(2)->toDateTimeString()]);
        $this->crearSalida([...$datos, 'tercero' => 'Persona de transporte']);
        $entrega = EntregaInventario::sole();
        $fila = $entrega->asignaciones()->sole();
        $ruta = route('operador.asignaciones.entrega', $entrega);
        $confirmacion = route('operador.asignaciones.confirmar_recepcion', $entrega);
        $this->get($ruta)->assertOk()->assertSee('Persona de transporte')->assertSee('Agregar datos de recepción (opcional)');
        $this->post($confirmacion, ['medio_confirmacion' => 'whatsapp'])->assertRedirect($ruta);
        $this->assertNull($entrega->refresh()->recibida_en);
        $this->assertNull($fila->refresh()->fecha_vencimiento);
        $confirmada = $entrega->confirmada_en;
        $this->post($confirmacion, ['recibida_en' => now()->subDay()->toDateTimeString()])->assertRedirect($ruta);
        $this->assertEquals($confirmada, $entrega->refresh()->confirmada_en);
        $this->assertNotNull($fila->refresh()->fecha_vencimiento);
        $documentos = route('operador.asignaciones.documento_entrega', $entrega);
        $pdf = fn ($nombre) => UploadedFile::fake()->create($nombre, 4, 'application/pdf');
        $this->post($documentos, ['pdf_firmado' => $pdf('firmado-1.pdf')])->assertRedirect($ruta);
        $this->post($documentos, ['pdf_firmado' => $pdf('firmado-2.pdf')])->assertRedirect($ruta);
        $this->post($documentos, ['foto' => UploadedFile::fake()->image('evidencia.png')])->assertRedirect($ruta);
        $this->get($ruta)->assertOk()->assertSee('Versión 1')->assertSee('Versión 2');
        $this->get(route('operador.asignaciones.index', ['seguimiento' => 'documentacion']))->assertViewHas('entregas', fn ($entregas) => $entregas->isEmpty());
        $this->assertCount(3, $entrega->documentos);
        $archivo = $entrega->documentos->first();
        Storage::disk('documentos')->assertExists($archivo->ruta);
        $descarga = route('operador.asignaciones.ver_pdf_firmado', $archivo);
        $this->get($descarga)->assertOk();
        $this->actingAs(User::factory()->create(['role_id' => 2, 'bodega_id' => $this->regional->id]))->get($descarga)->assertForbidden();
        $this->assertEquals(2, $this->saldo('nuevo'));
    }

    public function test_recordatorio_solo_alerta_por_pdf_y_notifica_responsables_autorizados(): void
    {
        $supervisor = User::factory()->create(['role_id' => 3]);
        $ajeno = User::factory()->create(['role_id' => 3]);
        $this->almacenista->supervisoresAsignados()->attach($supervisor);
        $this->crearSalida($this->datosSalida(['fecha' => now()->subHours(23)->toDateTimeString(), 'modalidad' => 'tercero']));
        $entrega = EntregaInventario::sole();
        Colaborador::create(['codigo' => 'C-ARCH', 'nombre' => 'Entrega respaldada', 'puesto' => 'Operación', 'estado' => 'Activo']);
        $this->crearSalida($this->datosSalida(['colaborador_codigo' => 'C-ARCH', 'modalidad' => 'tercero',
            'fecha' => now()->subHours(25)->toDateTimeString()]));
        $respaldada = AsignacionInventario::where('colaborador_codigo', 'C-ARCH')->sole()->entrega;
        $this->post(route('operador.asignaciones.documento_entrega', $respaldada), [
            'pdf_firmado' => UploadedFile::fake()->create('firmada.pdf', 2, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertNull($respaldada->confirmada_en);
        $this->artisan('inventario:recordar-entregas')->assertSuccessful();
        $this->assertDatabaseCount('entrega_avisos', 0);
        $this->travel(1)->hours();
        $this->artisan('inventario:recordar-entregas')->assertSuccessful();
        $this->assertDatabaseCount('entrega_avisos', 2);
        foreach ([$supervisor, $this->almacenista] as $usuario) {
            $aviso = $usuario->notifications()->get()->firstWhere('data.entidad_id', $entrega->id);
            $this->assertNotNull($aviso);
            $avisos = $usuario->notifications()->get()->where('data.tipo', 'asignacion_pendiente');
            $this->assertCount(1, $avisos);
            $this->assertStringContainsString('PDF de la hoja firmada', $avisos->sole()->data['mensaje']);
            $this->assertStringNotContainsString('recepción', $avisos->sole()->data['mensaje']);
        }
        $this->assertDatabaseMissing('entrega_avisos', ['user_id' => $ajeno->id]);
        $this->artisan('inventario:recordar-entregas')->assertSuccessful();
        $this->assertDatabaseCount('entrega_avisos', 2);
        $this->assertEquals(1, $this->saldo('nuevo'));
        $this->travelBack();
    }

    public function test_roles_personalizados_y_actuaciones_de_rrhh_con_evidencia(): void
    {
        $rol = Role::create(['nombre' => 'Custodia restringida', 'ambito_operativo' => 2, 'activo' => true]);
        DB::table('sistema_permisos_rol')->insert([
            ['role_id' => $rol->id, 'permiso' => 'asignaciones.consultar', 'permitido' => true],
            ['role_id' => $rol->id, 'permiso' => 'asignaciones.crear', 'permitido' => true],
        ]);
        $operador = User::factory()->create(['role_id' => $rol->id, 'bodega_id' => $this->regional->id]);
        $this->actingAs($operador);
        $this->crearSalida($this->datosSalida());
        $fila = AsignacionInventario::sole();
        $ruta = route('devoluciones.registrar', $fila->colaborador_codigo);
        $datos = $this->datosDevolucion($fila, 'danado');
        $this->postJson($ruta, $datos)->assertForbidden();
        DB::table('sistema_permisos_usuario')->insert([
            'user_id' => $operador->id, 'permiso' => 'asignaciones.devolver', 'permitido' => true,
        ]);
        $this->actingAs($operador->fresh())->post($ruta, $datos)->assertSessionHasNoErrors();

        $alerta = AlertaReemplazo::sole();
        $incidencia = $alerta->incidencia;
        $documento = route('operador.asignaciones.documento_incidencia', $incidencia);
        $archivo = UploadedFile::fake()->create('acta.pdf', 4, 'application/pdf');
        $this->postJson($documento, ['evidencia' => $archivo])->assertForbidden();
        $admin = User::factory()->create(['role_id' => 1]);
        $this->actingAs($admin)->post(route('admin.asignaciones.documento_incidencia', $incidencia), ['evidencia' => $archivo])
            ->assertSessionHasNoErrors();
        $detalle = route('rrhh.alertas.show', $alerta);
        $this->get($detalle)->assertForbidden();
        $this->actingAs($this->rrhh)->get($detalle)->assertOk()->assertSee('Daño')->assertSee('Respaldo del incidente');
        $this->get(route('rrhh.alertas.evidencia', $alerta))->assertOk();
        $this->post(route('rrhh.alertas.actuacion', $alerta), ['nota' => 'Solicitado el acta.'])->assertRedirect($detalle);
        $this->assertSame('en_revision', $alerta->fresh()->estado);
        $fin = route('rrhh.alertas.finalizar', $alerta);
        $this->post($fin, ['nota' => 'Acta revisada.', 'confirmar' => 1])->assertRedirect($detalle);
        $this->postJson($fin, ['nota' => 'Intento repetido.', 'confirmar' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('alertas_reemplazos_rrhh', ['id' => $alerta->id, 'estado' => 'finalizado', 'descuento_aplicable' => false]);
        $this->assertDatabaseHas('alerta_rrhh_actuaciones', ['alerta_id' => $alerta->id, 'usuario_id' => $this->rrhh->id, 'nota' => 'Acta revisada.']);
        $this->assertDatabaseCount('alerta_rrhh_actuaciones', 2);
        $this->get(route('rrhh.alertas.export'))->assertOk();
    }

    public function test_no_devuelto_genera_hoja_sin_ingreso_a_inventario(): void
    {
        $this->crearSalida($this->datosSalida());
        $asignacion = AsignacionInventario::sole();
        $this->post(route('devoluciones.registrar', $asignacion->colaborador_codigo), $this->datosDevolucion($asignacion, 'sin_retorno'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        $this->assertDatabaseHas('asignacion_inventario_archivos', ['tipo_documento' => 'devolucion_generada']);
        $this->assertDatabaseHas('incidencias_inventario', ['motivo' => 'otra', 'resultado_stock' => 'sin_retorno']);
    }

    public function test_hoja_firmada_es_del_grupo_y_el_respaldo_historico_sigue_disponible(): void
    {
        $otro = Producto::create(['codigo' => 'P02', 'nombre' => 'Casco', 'vida_util_meses' => 12]);
        DB::transaction(fn () => app(InventarioStockService::class)->incrementar($this->regional->id, $otro->codigo, 1));
        $datos = $this->datosSalida();
        $datos['items'][] = [...$datos['items'][0], 'producto_codigo' => $otro->codigo];
        $this->crearSalida($datos);
        $entrega = EntregaInventario::sole();
        $this->assertCount(2, $entrega->asignaciones);
        $this->get(route('operador.dashboard'))->assertOk()->assertDontSeeText('Hojas firmadas faltantes');
        $seguimiento = route('operador.asignaciones.index', ['seguimiento' => 'documentacion']);
        $respuesta = $this->get($seguimiento)->assertOk();
        $html = new \DOMDocument;
        $html->loadHTML($respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $navegacion = new \DOMXPath($html);
        $this->assertSame('1', trim($navegacion->evaluate(
            'string(//nav[@aria-label="Seguimiento de entregas"]/a[@aria-current="page"]/span)'
        )));
        $pdf = UploadedFile::fake()->create('hoja-grupal.pdf', 4, 'application/pdf');
        $this->post(route('operador.asignaciones.documento_entrega', $entrega), ['pdf_firmado' => $pdf])->assertSessionHasNoErrors();
        $this->assertCount(1, $entrega->documentos);
        $this->get($seguimiento)->assertOk()
            ->assertViewHas('entregas', fn ($entregas) => $entregas->isEmpty());
        $this->get(route('operador.asignaciones.pdf', $entrega->id))->assertOk()
            ->assertViewHas('asignaciones', fn ($filas) => $filas->count() === 2);

        $legado = AsignacionInventario::create([
            'grupo_asignacion' => (string) Str::uuid(), 'user_id' => $this->almacenista->id,
            'bodega_id' => $this->regional->id, 'colaborador_codigo' => 'C01', 'producto_codigo' => 'P01',
            'cantidad_asignada' => 0, 'estado' => 'Devuelta', 'fecha' => now()->subMonths(9),
        ]);
        $this->post(route('operador.asignaciones.upload_pdf_firmado', $legado), [
            'pdf_firmado' => UploadedFile::fake()->create('hoja-historica.pdf', 4, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $archivo = $legado->evidencias()->sole();
        $this->assertNull($archivo->grupo_entrega);
        $this->get(route('operador.asignaciones.ver_pdf_firmado', $archivo))->assertOk();
        $this->artisan('inventario:recordar-entregas')->assertSuccessful();
        $this->assertDatabaseCount('entrega_avisos', 0);
        $this->assertDatabaseCount('entregas_inventario', 1);
    }

    public function test_stock_agregado_conserva_vida_conocida_al_reutilizar_una_unidad(): void
    {
        config(['inventario.seguimiento_unidades' => false]);
        Producto::create(['codigo' => 'P-LOTE', 'nombre' => 'Equipo por lote', 'vida_util_meses' => 2]);
        DB::transaction(fn () => app(InventarioStockService::class)->incrementar($this->regional->id, 'P-LOTE', 3));
        $recibida = now()->subDays(5)->toDateTimeString();
        $primera = $this->datosSalida(['fecha' => $recibida, 'confirmar_recepcion' => true,
            'medio_confirmacion' => 'presencial', 'recibida_en' => $recibida]);
        $primera['items'][0]['producto_codigo'] = 'P-LOTE';
        $this->crearSalida($primera);
        $anterior = AsignacionInventario::sole();
        $this->post(route('devoluciones.registrar', $anterior->colaborador_codigo), $this->datosDevolucion($anterior))->assertSessionHasNoErrors();
        $vida = (int) DB::table('inventarios')->where('stock_tipo', 'usado')->value('vida_util_disponible_segundos');
        $this->assertGreaterThan(0, $vida);
        $fecha = now()->toDateTimeString();
        $datos = $this->datosSalida(['fecha' => $fecha, 'confirmar_recepcion' => true,
            'recibida_en' => $fecha, 'medio_confirmacion' => 'presencial']);
        Colaborador::create(['codigo' => 'C-LOTE', 'nombre' => 'Nuevo destinatario', 'puesto' => 'Técnico', 'estado' => 'Activo']);
        $datos['colaborador_codigo'] = 'C-LOTE';
        $datos['items'][0]['producto_codigo'] = 'P-LOTE';
        $this->crearSalida($datos);
        $nueva = AsignacionInventario::custodiaActiva()->sole();
        $this->assertSame('usado', $nueva->stock_tipo);
        $this->assertSame($vida, $nueva->vida_util_disponible_segundos);
        $this->assertEquals($anterior->id, InventarioLoteOperativo::findOrFail($nueva->lote_operativo_id)->retornado_desde_id);
        $this->assertEquals($nueva->entrega->recibida_en->copy()->addSeconds($vida), $nueva->fecha_vencimiento);
        $this->assertEquals(0, $this->saldo('usado'));
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 0);
    }

    #[DataProvider('modosInventario')]
    public function test_peps_considera_ingreso_real_y_conserva_la_fecha_al_trasladar(bool $porUnidad): void
    {
        config(['inventario.seguimiento_unidades' => $porUnidad]);
        $producto = Producto::create(['codigo' => 'P-PEPS', 'nombre' => 'Equipo PEPS', 'vida_util_meses' => 6]);
        $antigua = now()->subDays(10)->startOfDay();
        $stock = app(InventarioStockService::class);
        DB::transaction(function () use ($stock, $producto, $antigua) {
            $stock->incrementar($this->central->id, $producto->codigo, 1, 15, now()->subDay());
            $stock->incrementar($this->central->id, $producto->codigo, 1, 30, $antigua);
            $stock->trasladar($this->central->id, $this->regional->id, $producto->codigo, 2);
        });
        $datos = $this->datosSalida();
        $datos['items'][0]['producto_codigo'] = $producto->codigo;
        $this->crearSalida($datos);
        $asignacion = AsignacionInventario::sole();
        $ingreso = $porUnidad
            ? $asignacion->periodos()->sole()->existencia->ingresado_en
            : InventarioLoteOperativo::findOrFail($asignacion->lote_operativo_id)->ingresado_en;
        $this->assertEquals($antigua, $ingreso);
        $this->assertSame('30.00', $asignacion->costo_unitario);
        $this->assertDatabaseHas('inventarios', ['bodega_id' => $this->central->id, 'producto_codigo' => $producto->codigo, 'cantidad' => 0]);
        $this->assertDatabaseHas('inventarios', ['bodega_id' => $this->regional->id, 'producto_codigo' => $producto->codigo, 'cantidad' => 1]);
    }

    public static function modosInventario(): array
    {
        return ['por unidad' => [true], 'por lotes' => [false]];
    }

    public function test_lotes_usados_mantienen_vidas_distintas_y_primer_saldo_es_el_menor_incluso_vencido(): void
    {
        config(['inventario.seguimiento_unidades' => false]);
        $codigo = 'P-VIDAS';
        Producto::create(['codigo' => $codigo, 'nombre' => 'Equipo con distintos períodos', 'vida_util_meses' => 2]);
        DB::transaction(fn () => app(InventarioStockService::class)->incrementar($this->regional->id, $codigo, 3));
        $t0 = now()->startOfDay();
        foreach (['CU-A', 'CU-B', 'CU-C'] as $persona) {
            Colaborador::create(['codigo' => $persona, 'nombre' => $persona, 'puesto' => 'Pruebas', 'estado' => 'Activo']);
        }
        $entregar = function (string $persona, $fecha) use ($codigo) {
            $datos = $this->datosSalida(['colaborador_codigo' => $persona, 'fecha' => $fecha->toDateTimeString()]);
            $datos['items'][0]['producto_codigo'] = $codigo;
            $this->crearSalida($datos);

            return AsignacionInventario::where('colaborador_codigo', $persona)->latest('id')->firstOrFail();
        };
        $vencida = $entregar('CU-A', $t0->copy()->subDays(80));
        $vigente = $entregar('CU-B', $t0->copy()->subDays(20));
        $this->travelTo($t0);
        foreach ([$vencida, $vigente] as $fila) {
            $this->post(route('devoluciones.registrar', $fila->colaborador_codigo), $this->datosDevolucion($fila))->assertSessionHasNoErrors();
        }
        $this->assertSame([0, 40 * 86400], InventarioLoteOperativo::where('producto_codigo', $codigo)
            ->where('stock_tipo', 'usado')->orderBy('vida_restante_segundos')->pluck('vida_restante_segundos')->all());
        $this->travel(7)->days();
        $primera = $entregar('CU-C', now());
        $segunda = $entregar('C01', now());
        $this->assertSame('usado', $primera->stock_tipo);
        $this->assertSame(0, $primera->vida_util_disponible_segundos);
        $this->assertSame('usado', $segunda->stock_tipo);
        $this->assertSame(40 * 86400, $segunda->vida_util_disponible_segundos);
        $this->assertSame(2, (int) $segunda->vida_util_original_meses);
        $this->assertDatabaseHas('inventarios', ['producto_codigo' => $codigo, 'stock_tipo' => 'nuevo', 'cantidad' => 1]);
        $this->assertDatabaseHas('inventarios', ['producto_codigo' => $codigo, 'stock_tipo' => 'usado', 'cantidad' => 0]);
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 0);
        $this->artisan('inventario:contrastar-saldos', ['--lotes' => true])->assertSuccessful();
        $this->travelBack();
    }

    public function test_excepcion_de_inventario_nuevo_requiere_permiso_y_guarda_motivo(): void
    {
        $this->crearSalida($this->datosSalida());
        $asignacion = AsignacionInventario::sole();
        $this->post(route('devoluciones.registrar', $asignacion->colaborador_codigo), $this->datosDevolucion($asignacion))->assertSessionHasNoErrors();

        $this->actingAs($this->almacenista);
        $datos = $this->datosSalida();
        $datos['items'][0]['motivo_inventario_nuevo'] = 'Requisito del área de trabajo';
        $this->postJson(route('operador.asignaciones.store'), $datos)->assertForbidden();
        DB::table('sistema_permisos_usuario')->insert([
            'user_id' => $this->almacenista->id, 'permiso' => 'asignaciones.entregar_nuevo', 'permitido' => true,
        ]);
        $this->actingAs($this->almacenista->fresh());
        $this->crearSalida($datos);
        $this->assertDatabaseHas('asignaciones_inventarios', ['stock_tipo' => 'nuevo',
            'motivo_inventario_nuevo' => 'Requisito del área de trabajo', 'tipo_entrega' => 'adicional', 'asignacion_anterior_id' => null]);
        $this->assertEquals(1, $this->saldo('usado'));
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 0);
    }

    #[DataProvider('situacionesReemplazo')]
    public function test_reemplazo_distingue_vida_vencida_de_datos_insuficientes(string $modalidad, ?string $motivo): void
    {
        $this->crearSalida($this->datosSalida(['fecha' => now()->subDays(90)->toDateTimeString(), 'modalidad' => $modalidad]));
        $this->crearSalida($this->reemplazar(AsignacionInventario::sole(), $motivo === null ? 'desgaste' : 'robo'));
        $this->assertDatabaseCount('incidencias_inventario', 0);
        $this->assertDatabaseCount('asignacion_movimientos', 2);
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', $motivo === null ? 0 : 1);
        if ($motivo !== null) {
            $this->assertDatabaseHas('alertas_reemplazos_rrhh', ['motivo_revision' => $motivo, 'vida_restante_segundos' => null]);
        }
        $anterior = AsignacionInventario::oldest('id')->firstOrFail();
        $this->assertSame('Activa', $anterior->estado);
        $this->assertNotNull($anterior->sustituida_por_id);
    }

    public static function situacionesReemplazo(): array
    {
        return ['vencida' => ['directa', null], 'desconocida' => ['tercero', 'motivo_asignacion']];
    }

    public function test_expediente_muestra_custodias_incidencias_y_documentos_tras_el_reemplazo(): void
    {
        $this->crearSalida($this->datosSalida());
        $anterior = AsignacionInventario::sole();
        $this->post(route('operador.asignaciones.documento_entrega', $anterior->grupo_asignacion), [
            'pdf_firmado' => UploadedFile::fake()->create('respaldo.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->crearSalida($this->reemplazar($anterior, 'robo'));
        $admin = User::factory()->create(['role_id' => 1]);
        $url = route('admin.colaboradores.detalle', 'C01');
        $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('asignaciones.0.tipo_entrega', 'Reemplazo')
            ->assertJsonPath('asignaciones.1.custodia', 'Custodia anterior sin retorno confirmado')
            ->assertJsonPath('asignaciones.1.documentos.0.nombre', 'respaldo.pdf')
            ->assertJsonPath('incidencias.0.motivo', 'Robo');
        $nueva = AsignacionInventario::custodiaActiva()->sole();
        $datos = $this->datosDevolucion($anterior, 'sin_retorno');
        $datos['articulos'] += $this->datosDevolucion($nueva, 'sin_retorno')['articulos'];
        $this->actingAs($this->almacenista)->post(route('devoluciones.registrar', $anterior->colaborador_codigo), $datos)->assertSessionHasNoErrors();
        $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('asignaciones.1.custodia', 'Cerrada')
            ->assertJsonPath('asignaciones.1.incidencias.0.condicion', 'Otra incidencia')
            ->assertJsonCount(0, 'asignaciones.1.retornos');
        $this->assertDatabaseCount('alertas_reemplazos_rrhh', 1);
        $this->get(route('admin.colaboradores.ficha_tecnica', 'C01'))->assertOk();
    }

    private function reemplazar(AsignacionInventario $anterior, string $motivo): array
    {
        $datos = $this->datosSalida();
        $datos['items'][0] += ['sustituir_asignacion_id' => $anterior->id, 'motivo_asignacion' => $motivo];

        return $datos;
    }

    private function datosSalida(array $cambios = []): array
    {
        return $cambios + [
            'solicitud_id' => (string) Str::uuid(), 'colaborador_codigo' => 'C01', 'fecha' => now()->toDateTimeString(),
            'aprobado_por' => 'Gerencia', 'medio_solicitud' => 'Correo',
            'items' => [['producto_codigo' => 'P01', 'bodega_id' => $this->regional->id, 'cantidad_asignada' => 1]],
        ];
    }

    private function crearSalida(array $datos): void
    {
        $this->post(route('operador.asignaciones.store'), $datos)->assertSessionHasNoErrors()->assertRedirect();
    }

    private function saldo(string $tipo): int
    {
        return (int) DB::table('inventarios')->where('bodega_id', $this->regional->id)->where('stock_tipo', $tipo)->value('cantidad');
    }

    private function agregarStock(Bodega $bodega, int $cantidad): void
    {
        DB::transaction(fn () => app(InventarioStockService::class)->incrementar($bodega->id, 'P01', $cantidad));
    }
}
