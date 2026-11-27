<?php

namespace Tests\Feature;

use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Consolidado;
use App\Models\DelegacionOperativa;
use App\Models\ImportacionAsignacion;
use App\Models\Inventario;
use App\Models\Operacion;
use App\Models\Producto;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\ExcelAsignaciones;
use App\Services\InventarioStockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\ConDevolucionesCompletas;
use Tests\TestCase;

class InventarioPermisosConfigurablesTest extends TestCase
{
    use RefreshDatabase, ConDevolucionesCompletas;

    private User $admin;
    private User $coordinador;
    private User $rrhh;
    private Bodega $central;
    private Bodega $regional;
    private Bodega $otraBodega;
    private Region $norte;
    private Region $sur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inventario.seguimiento_unidades' => false, 'modulos.vehiculos' => false]);
        foreach ([1 => 'Administrador', 2 => 'Operador', 3 => 'Supervisor', 4 => 'Recursos Humanos'] as $id => $nombre) {
            DB::table('roles')->insert(compact('id', 'nombre'));
        }
        $this->admin = User::factory()->create(['role_id' => 1]);
        DB::table('sistema_superadministradores')->insert(['user_id' => $this->admin->id, 'activo' => true]);
        $this->coordinador = User::factory()->create(['role_id' => Role::where('ambito_operativo', 5)->sole()->id]);
        $this->rrhh = User::factory()->create(['role_id' => 4]);
        $this->central = Bodega::create(['nombre' => 'Bodega central QA', 'tipo' => 'Principal']);
        $this->regional = Bodega::create(['nombre' => 'Bodega regional QA', 'tipo' => 'Regional']);
        $this->otraBodega = Bodega::create(['nombre' => 'Bodega ajena QA', 'tipo' => 'Regional']);
        $this->norte = Region::create(['nombre' => 'Norte QA', 'activa' => true]);
        $this->sur = Region::create(['nombre' => 'Sur QA', 'activa' => true]);
        foreach (['NORTE-01' => $this->norte, 'SUR-01' => $this->sur] as $codigo => $region) {
            Colaborador::create(['codigo' => $codigo, 'nombre' => 'Persona '.$codigo, 'region_id' => $region->id, 'puesto' => 'Técnico', 'estado' => 'Activo']);
        }
        foreach (['EQUIPO-A', 'EQUIPO-B'] as $codigo) {
            Producto::create(['codigo' => $codigo, 'nombre' => $codigo, 'vida_util_meses' => 12]);
            foreach ([$this->central, $this->regional, $this->otraBodega] as $bodega) {
                DB::transaction(fn () => app(InventarioStockService::class)->incrementar($bodega->id, $codigo, 10, 12));
            }
        }
    }

    public function test_compra_requiere_permiso_y_bodega_central_y_no_repite_factura_o_movimiento(): void
    {
        $ruta = route('operador.compras.store');
        $datos = $this->factura('QA-PERM-001');
        $this->alistar($this->coordinador, ['compras.crear', 'compras.consultar'], [], consultar: [$this->central->id]);
        $this->actingAs($this->coordinador->fresh())->postJson($ruta, $datos)->assertForbidden();
        $this->alistar($this->coordinador, [], [$this->regional->id]);
        $this->actingAs($this->coordinador->fresh())->postJson($ruta, $datos)->assertForbidden();
        $this->alistar($this->coordinador, [], [$this->central->id]);
        $this->actingAs($this->coordinador->fresh())->get(route('operador.compras.create'))->assertOk();
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseHas('compras', ['no_factura' => 'QA-PERM-001', 'bodega_id' => $this->central->id]);
        $this->assertDatabaseHas('movimientos', ['tipo_movimiento' => 'Entrada', 'user_id' => $this->coordinador->id, 'cantidad' => 2]);
        $this->assertSame(12, $this->stock($this->central));
        $this->assertSame(10, $this->stock($this->regional));
        $this->get(route('operador.compras.index'))->assertOk()->assertSee('QA-PERM-001');
        $this->get(route('operador.compras.index', ['xlsx' => 1]))->assertForbidden();
        $this->get(route('admin.usuarios.index'))->assertForbidden();
        $this->assertSame($this->coordinador->role_id, $this->coordinador->fresh()->role_id);
    }

    public function test_listado_de_compras_y_panel_muestran_acciones_segun_permiso_efectivo(): void
    {
        $this->alistar($this->coordinador, ['compras.consultar'], [$this->central->id]);
        $listado = route('operador.compras.index');
        $registro = route('operador.compras.create');
        $this->actingAs($this->coordinador->fresh())->get(route('coordinacion.dashboard'))
            ->assertOk()->assertSee($listado, false);
        $this->get($listado)->assertOk()->assertDontSeeText('Consultar datos')
            ->assertDontSeeText('Registrar compra')->assertDontSee('id="modalCompra"', false);
        $this->get($registro)->assertForbidden();

        $this->alistar($this->coordinador, ['compras.crear', 'compras.consultar'], [$this->central->id]);
        $this->actingAs($this->coordinador->fresh())->get($listado)->assertOk()
            ->assertSeeText('Registrar compra')->assertSee('id="modalCompra"', false);
        $this->get($registro)->assertOk();
    }

    public function test_bodegas_muestra_traslados_solo_para_los_origenes_autorizados(): void
    {
        $operador = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->central->id]);
        $listado = route('operador.bodegas.index');
        $traslado = fn (Bodega $bodega) => route('operador.operaciones.traslados.create', ['origen' => $bodega->id]);
        $this->actingAs($operador)->get($listado)->assertOk()
            ->assertSeeText('Bodega asignada')->assertSeeText('Solo consulta')->assertDontSeeText('Consultar datos')
            ->assertSee($traslado($this->central), false)
            ->assertDontSee($traslado($this->regional), false);

        $this->alistar($operador, ['traslados.crear'], [$this->central->id, $this->regional->id]);
        $this->actingAs($operador->fresh())->get($listado)->assertOk()
            ->assertSeeText('Acceso operativo')->assertSee($traslado($this->regional), false)
            ->assertDontSee($traslado($this->otraBodega), false);
    }

    public function test_custodias_se_filtran_por_region_y_bodega_en_formulario_listado_y_lotes(): void
    {
        $this->alistar($this->coordinador, ['asignaciones.crear', 'asignaciones.consultar', 'asignaciones.devolver'], [$this->central->id], [$this->norte->id]);
        $ruta = route('operador.asignaciones.store');
        $this->actingAs($this->coordinador->fresh())->get(route('operador.asignaciones.create'))
            ->assertOk()->assertSee('Persona NORTE-01')->assertDontSee('Persona SUR-01')->assertDontSee('Bodega ajena QA');
        $this->getJson(route('operador.asignaciones.contexto', ['colaborador_codigo' => 'SUR-01']))->assertForbidden();
        $this->postJson($ruta, $this->entrega('SUR-01', $this->central))->assertForbidden();
        $this->postJson($ruta, $this->entrega('NORTE-01', $this->regional))->assertForbidden();
        $this->post($ruta, $this->entrega('NORTE-01', $this->central))->assertSessionHasNoErrors();
        $asignacion = AsignacionInventario::sole();
        $this->assertSame($this->coordinador->id, $asignacion->user_id);
        $this->actingAs($this->admin)->post($ruta, $this->entrega('SUR-01', $this->central, 'EQUIPO-B'))->assertSessionHasNoErrors();
        $fuera = AsignacionInventario::where('colaborador_codigo', 'SUR-01')->sole();
        $this->actingAs($this->coordinador->fresh())->get(route('operador.asignaciones.index'))
            ->assertOk()->assertSee('Persona NORTE-01')->assertDontSee('Persona SUR-01');
        $this->get(route('sistema.datos.ficha', ['asignaciones', $fuera->id]))->assertNotFound();
        $this->get(route('operador.asignaciones.entrega', $fuera->grupo_asignacion))->assertForbidden();
        $this->postJson(route('devoluciones.registrar', $fuera->colaborador_codigo), $this->datosDevolucion($fuera))
            ->assertForbidden();
        $this->assertSame(1, $asignacion->fresh()->cantidad_asignada);
        $this->assertSame(9, $this->stock($this->central));
        $this->post(route('devoluciones.registrar', $asignacion->colaborador_codigo), $this->datosDevolucion($asignacion))->assertSessionHasNoErrors();
        $this->assertSame(0, $asignacion->fresh()->cantidad_asignada);
        $this->assertDatabaseHas('asignacion_movimientos', ['asignacion_inventario_id' => $asignacion->id, 'user_id' => $this->coordinador->id, 'tipo' => 'Devolucion']);
        $this->assertSame(1, $this->stock($this->central, tipo: 'usado'));
    }

    public function test_traslado_exige_origen_y_destino_autorizados_y_solo_destino_puede_decidir(): void
    {
        $this->alistar($this->coordinador, ['traslados.crear', 'traslados.consultar', 'traslados.exportar', 'traslados.aprobar'], [$this->central->id, $this->regional->id]);
        $this->alistar($this->rrhh, ['traslados.consultar', 'traslados.aprobar', 'traslados.rechazar'], [$this->otraBodega->id]);
        $ruta = route('operador.operaciones.traslados.store');
        $this->actingAs($this->coordinador->fresh())->postJson($ruta, $this->traslado($this->otraBodega))->assertForbidden();
        $this->post($ruta, $this->traslado($this->regional))->assertSessionHasNoErrors();
        $fila = Operacion::sole();
        $this->assertSame($this->coordinador->id, $fila->creado_por);
        $this->assertSame(10, $this->stock($this->central));
        $hoja = route('operador.operaciones.traslados.hoja', $fila);
        $this->get(route('operador.operaciones.traslados.show', $fila))->assertOk()
            ->assertSee('href="'.$hoja.'"', false)->assertSee('target="_blank" rel="noopener"', false);
        $this->get($hoja)->assertOk()->assertSeeText('Hoja de traslado')
            ->assertSeeText('Imprimir / Guardar PDF');
        $aprobar = route('operador.operaciones.traslados.aprobar', $fila);
        $this->postJson($aprobar)->assertForbidden();
        $this->actingAs($this->rrhh->fresh())->postJson($aprobar)->assertForbidden();
        $this->get(route('operador.operaciones.traslados.show', $fila))->assertForbidden();
        $this->alistar($this->rrhh, [], [$this->regional->id]);
        $this->actingAs($this->rrhh->fresh())->post($aprobar)->assertSessionHasNoErrors();
        $this->postJson($aprobar)->assertUnprocessable();
        $this->assertSame($this->rrhh->id, $fila->fresh()->aprobado_por);
        $this->assertSame(8, $this->stock($this->central));
        $this->assertSame(12, $this->stock($this->regional));
        $this->actingAs($this->coordinador->fresh())->post($ruta, $this->traslado($this->regional))->assertSessionHasNoErrors();
        $segunda = Operacion::latest('id')->firstOrFail();
        $this->actingAs($this->rrhh->fresh())->post(route('operador.operaciones.traslados.rechazar', $segunda),
            ['motivo_rechazo' => 'Diferencia de solicitud'])->assertSessionHasNoErrors();
        $this->assertSame($this->rrhh->id, $segunda->fresh()->rechazado_por);
        $this->assertSame(8, $this->stock($this->central));
        $this->assertSame(12, $this->stock($this->regional));
    }

    public function test_delegaciones_autorizan_operaciones_con_actor_propio_y_vencen_sin_mover_stock_adicional(): void
    {
        $permisos = ['compras.crear', 'asignaciones.crear', 'asignaciones.devolver', 'flujo.importar'];
        $this->alistar($this->coordinador, $permisos, [$this->central->id], [$this->norte->id]);
        $this->travelTo(now()->startOfMinute());
        $delegacion = $this->delegar($this->coordinador, $this->rrhh, $permisos, [$this->central->id], [$this->norte->id]);
        $compra = route('operador.compras.store');
        $this->actingAs($this->rrhh->fresh())->postJson($compra, $this->factura('ANTES'))->assertForbidden();
        $this->travel(20)->minutes();
        $this->post($compra, $this->factura('DELEGADA'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('movimientos', ['user_id' => $this->rrhh->id, 'tipo_movimiento' => 'Entrada']);
        $manual = route('operador.asignaciones.store');
        $this->postJson($manual, $this->entrega('SUR-01', $this->central))->assertForbidden();
        $this->post($manual, $this->entrega('NORTE-01', $this->central))->assertSessionHasNoErrors();
        $fila = AsignacionInventario::sole();
        $this->post(route('devoluciones.registrar', $fila->colaborador_codigo), $this->datosDevolucion($fila))->assertSessionHasNoErrors();
        $this->assertSame($this->rrhh->id, $fila->user_id);
        $this->post(route('flujo.importar.preview'), ['excel' => $this->excel('NORTE-01', 'EQUIPO-B')])->assertRedirect()->assertSessionHasNoErrors();
        $importacion = ImportacionAsignacion::sole();
        $this->post(route('flujo.importar.confirmar', $importacion))->assertSessionHasNoErrors();
        $this->post(route('flujo.importar.confirmar', $importacion))->assertSessionHasNoErrors();
        $this->assertSame(9, $this->stock($this->central, 'EQUIPO-B'));
        $this->assertDatabaseHas('sistema_auditoria', ['actor_id' => $this->rrhh->id, 'accion' => 'usar_delegacion', 'registro' => $importacion->id]);
        $this->travelTo($delegacion->vigente_hasta);
        $this->postJson($compra, $this->factura('VENCIDA'))->assertForbidden();
        $this->postJson(route('flujo.importar.confirmar', $importacion))->assertForbidden();
        $this->assertDatabaseCount('compras', 1);
        $this->assertSame(9, $this->stock($this->central, 'EQUIPO-B'));
    }

    public function test_importacion_revalida_region_y_revocacion_despues_de_vista_previa(): void
    {
        $permisos = ['asignaciones.crear', 'flujo.importar'];
        $this->alistar($this->coordinador, $permisos, [$this->central->id], [$this->norte->id]);
        $this->travelTo(now()->startOfMinute());
        $delegacion = $this->delegar($this->coordinador, $this->rrhh, $permisos, [$this->central->id], [$this->norte->id]);
        $this->travel(20)->minutes();
        $this->actingAs($this->rrhh->fresh())->post(route('flujo.importar.preview'), ['excel' => $this->excel('SUR-01')])->assertForbidden();
        $this->post(route('flujo.importar.preview'), ['excel' => $this->excel('NORTE-01')])->assertRedirect()->assertSessionHasNoErrors();
        $lote = ImportacionAsignacion::sole();
        $confirmar = route('flujo.importar.confirmar', $lote);
        Colaborador::whereKey('NORTE-01')->update(['region_id' => $this->sur->id]);
        $this->postJson($confirmar)->assertForbidden();
        Colaborador::whereKey('NORTE-01')->update(['region_id' => $this->norte->id]);
        $this->actingAs($this->admin)->patch(route('sistema.usuario.delegaciones.revocar', [$this->rrhh, $delegacion]),
            ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->actingAs($this->rrhh->fresh())->postJson($confirmar)->assertForbidden();
        $this->assertNull($lote->fresh()->confirmada_en);
        $this->assertDatabaseCount('asignaciones_inventarios', 0);
        $this->assertSame(10, $this->stock($this->central));
    }

    public function test_traslado_delegado_rechaza_escalamiento_y_la_decision_queda_bajo_la_identidad_del_sustituto(): void
    {
        $permisos = ['traslados.aprobar'];
        $this->alistar($this->coordinador, $permisos, [$this->regional->id]);
        $this->actingAs($this->admin)->post(route('admin.operaciones.traslados.store'), $this->traslado($this->regional))->assertSessionHasNoErrors();
        $fila = Operacion::sole();
        $this->travelTo(now()->startOfMinute());
        $url = route('sistema.usuario.delegaciones.store', $this->rrhh);
        $base = [
            'titular_id' => $this->coordinador->id, 'permisos' => $permisos,
            'bodegas' => [$this->otraBodega->id], 'vigente_desde' => now()->format('Y-m-d\TH:i'),
            'vigente_hasta' => now()->addHour()->format('Y-m-d\TH:i'), 'motivo' => 'Cobertura', 'current_password' => 'password',
        ];
        $this->postJson($url, $base)->assertUnprocessable()->assertJsonValidationErrors('bodegas');
        $this->postJson($url, [...$base, 'bodegas' => [$this->regional->id], 'permisos' => ['traslados.rechazar']])
            ->assertUnprocessable()->assertJsonValidationErrors('permisos');
        $this->actingAs($this->rrhh)->postJson($url, $base)->assertForbidden();
        $delegacion = $this->delegar($this->coordinador, $this->rrhh, $permisos, [$this->regional->id]);
        $this->travel(20)->minutes();
        $this->actingAs($this->rrhh)->post(route('operador.operaciones.traslados.aprobar', $fila))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('movimientos', ['tipo_movimiento' => 'Traslado', 'user_id' => $this->rrhh->id, 'cantidad' => 2]);
        $this->assertSame($this->rrhh->id, $fila->fresh()->aprobado_por);
        $bitacora = DB::table('sistema_auditoria')->where('accion', 'usar_delegacion')->where('registro', $fila->id)->sole();
        $this->assertSame($delegacion->id, json_decode($bitacora->despues, true)['delegacion_id']);
    }

    public function test_dos_delegaciones_no_combinan_la_region_de_una_con_la_bodega_de_otra(): void
    {
        $permisos = ['asignaciones.crear'];
        $titularRegional = User::factory()->create(['role_id' => $this->coordinador->role_id]);
        $this->alistar($this->coordinador, $permisos, [$this->central->id], [$this->norte->id]);
        $this->alistar($titularRegional, $permisos, [$this->regional->id], [$this->sur->id]);
        $this->travelTo(now()->startOfMinute());
        $this->delegar($this->coordinador, $this->rrhh, $permisos, [$this->central->id], [$this->norte->id]);
        $this->delegar($titularRegional, $this->rrhh, $permisos, [$this->regional->id], [$this->sur->id]);
        $this->travel(20)->minutes();
        $ruta = route('operador.asignaciones.store');
        $this->actingAs($this->rrhh)->postJson($ruta, $this->entrega('NORTE-01', $this->regional))->assertForbidden();
        $this->postJson($ruta, $this->entrega('SUR-01', $this->central))->assertForbidden();
        $this->post($ruta, $this->entrega('SUR-01', $this->regional))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignaciones_inventarios', ['user_id' => $this->rrhh->id, 'bodega_id' => $this->regional->id, 'colaborador_codigo' => 'SUR-01']);
        $this->assertSame(10, $this->stock($this->central));
    }

    public function test_vincular_excel_exige_permiso_adicional_y_filtra_el_consolidado_por_colaborador(): void
    {
        $consolidado = Consolidado::create(['operador_id' => $this->admin->id, 'coordinador_id' => $this->coordinador->id,
            'nombre' => 'Requerimiento aprobado QA', 'desde' => today(), 'hasta' => today(), 'estado' => 'aprobado']);
        $consolidado->forceFill(['detalle_individual' => true])->save();
        foreach (['NORTE-01' => $this->norte, 'SUR-01' => $this->sur] as $codigo => $region) {
            $consolidado->participantes()->create(['colaborador_codigo' => $codigo, 'region_id' => $region->id])
                ->necesidades()->create(['producto_codigo' => 'EQUIPO-A', 'original' => 1, 'cantidad' => 1]);
        }
        $permisos = ['asignaciones.crear', 'flujo.importar'];
        $this->alistar($this->coordinador, $permisos, [$this->central->id], [$this->norte->id]);
        $ruta = route('flujo.importar.pendientes', ['consolidado_id' => $consolidado->id]);
        $this->actingAs($this->coordinador->fresh())->get($ruta)->assertForbidden();
        $this->alistar($this->coordinador, ['asignaciones.vincular'], [$this->central->id], [$this->norte->id]);
        $respuesta = $this->actingAs($this->coordinador->fresh())->get($ruta)->assertOk();
        $temporal = tempnam(sys_get_temp_dir(), 'edson-alcance-');
        try {
            file_put_contents($temporal, $respuesta->streamedContent());
            $hoja = \PhpOffice\PhpSpreadsheet\IOFactory::load($temporal)->getSheetByName('Asignaciones');
            $this->assertSame('NORTE-01', (string) $hoja->getCell('A2')->getValue());
            $this->assertNull($hoja->getCell('A3')->getValue());
        } finally {
            unlink($temporal);
        }
        $this->post(route('flujo.importar.preview'), ['consolidado_id' => $consolidado->id, 'excel' => $this->excel('NORTE-01')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $lote = ImportacionAsignacion::sole();
        $this->post(route('flujo.importar.confirmar', $lote))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignaciones_inventarios', ['user_id' => $this->coordinador->id, 'colaborador_codigo' => 'NORTE-01']);
        $this->assertNotNull(AsignacionInventario::sole()->requerimiento_id);
    }

    private function alistar(User $usuario, array $permisos, array $bodegas, array $regiones = [], array $consultar = []): void
    {
        $this->actingAs($this->admin)->put(route('sistema.usuario.relaciones', $usuario), [
            'bodegas_inventario' => $bodegas, 'regiones' => $regiones, 'bodegas_operativas' => $consultar, 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        if ($permisos !== []) {
            $this->put(route('sistema.usuario.permisos', $usuario), [
                'reglas' => array_fill_keys($permisos, 'permitir'), 'current_password' => 'password',
            ])->assertSessionHasNoErrors();
        }
    }

    private function delegar(User $titular, User $sustituto, array $permisos, array $bodegas, array $regiones = []): DelegacionOperativa
    {
        $this->actingAs($this->admin)->post(route('sistema.usuario.delegaciones.store', $sustituto), [
            'titular_id' => $titular->id, 'permisos' => $permisos, 'bodegas' => $bodegas, 'regiones' => $regiones,
            'vigente_desde' => now()->addMinutes(10)->format('Y-m-d\TH:i'), 'vigente_hasta' => now()->addHour()->format('Y-m-d\TH:i'),
            'motivo' => 'Cobertura autorizada', 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        return DelegacionOperativa::latest('id')->firstOrFail();
    }

    private function factura(string $numero): array
    {
        return ['fecha_compra' => today()->toDateString(), 'no_factura' => $numero, 'proveedor_tipo' => 'nuevo',
            'proveedor_nombre' => 'Proveedor QA', 'forma_pago' => 'Al contado', 'producto_tipo' => ['existente'],
            'producto_codigo' => ['EQUIPO-A'], 'cantidad' => [2], 'precio_unitario' => ['12.00']];
    }

    private function entrega(string $codigo, Bodega $bodega, string $producto = 'EQUIPO-A'): array
    {
        return ['solicitud_id' => (string) Str::uuid(), 'colaborador_codigo' => $codigo, 'fecha' => today()->toDateString(),
            'items' => [['producto_codigo' => $producto, 'bodega_id' => $bodega->id, 'cantidad_asignada' => 1]]];
    }


    private function traslado(Bodega $destino): array
    {
        return ['solicitud_id' => (string) Str::uuid(), 'bodega_origen_id' => $this->central->id, 'bodega_destino_id' => $destino->id,
            'lineas' => [['producto_codigo' => 'EQUIPO-A', 'cantidad' => 2]]];
    }

    private function excel(string $colaborador, string $producto = 'EQUIPO-A'): UploadedFile
    {
        $archivo = app(ExcelAsignaciones::class)->plantilla();
        $archivo->getSpreadsheet()->getSheetByName('Asignaciones')
            ->fromArray([[$colaborador, $producto, 1, today()->toDateString(), 'Autorización QA', 'Correo', '', 'Prueba']], startCell: 'A2');
        ob_start();
        $archivo->save('php://output');

        return UploadedFile::fake()->createWithContent('asignaciones.xlsx', ob_get_clean());
    }

    private function stock(Bodega $bodega, string $producto = 'EQUIPO-A', string $tipo = 'nuevo'): int
    {
        return (int) Inventario::where('bodega_id', $bodega->id)->where('producto_codigo', $producto)->where('stock_tipo', $tipo)->sum('cantidad');
    }
}
