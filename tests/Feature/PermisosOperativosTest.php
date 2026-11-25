<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Consolidado;
use App\Models\CorteRequerimiento;
use App\Models\DelegacionOperativa;
use App\Models\Inspeccion;
use App\Models\ProcesoInspeccion;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\CortesRequerimientos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class PermisosOperativosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operador;
    private User $supervisor;
    private User $coordinador;
    private User $otroCoordinador;
    private User $rrhh;
    private Region $norte;
    private Region $sur;
    private Bodega $central;
    private Bodega $regional;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach ([1 => 'Administrador', 2 => 'Operador', 3 => 'Supervisor', 4 => 'RR. HH.'] as $id => $nombre) {
            DB::table('roles')->insert(compact('id', 'nombre'));
        }
        $coord = Role::where('ambito_operativo', 5)->sole();
        $this->admin = User::factory()->create(['role_id' => 1]);
        DB::table('sistema_superadministradores')->insert(['user_id' => $this->admin->id, 'activo' => true]);
        $this->central = Bodega::create(['nombre' => 'Central QA', 'tipo' => 'Principal']);
        $this->regional = Bodega::create(['nombre' => 'Regional QA', 'tipo' => 'Regional']);
        $this->operador = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->central->id]);
        $this->supervisor = User::factory()->create(['role_id' => 3]);
        $this->coordinador = User::factory()->create(['role_id' => $coord->id]);
        $this->otroCoordinador = User::factory()->create(['role_id' => $coord->id]);
        $this->rrhh = User::factory()->create(['role_id' => 4]);
        $this->norte = Region::create(['nombre' => 'Norte QA', 'activa' => true]);
        $this->sur = Region::create(['nombre' => 'Sur QA', 'activa' => true]);
        $this->norte->coordinador()->associate($this->coordinador)->save();
        $this->sur->coordinador()->associate($this->otroCoordinador)->save();
        $this->supervisor->regionesOperativas()->attach($this->norte);
        foreach ([$this->norte, $this->sur] as $region) {
            Colaborador::create(['codigo' => 'COL-'.$region->id, 'nombre' => 'Persona '.$region->nombre,
                'puesto' => 'Pruebas', 'estado' => 'Activo', 'region_id' => $region->id]);
        }
    }

    public function test_cualquier_cargo_puede_capturar_con_permiso_y_relacion_y_retirarlo_surte_efecto(): void
    {
        $direccion = route('flujo.inspecciones.store');
        $proceso = ProcesoInspeccion::where('nombre', 'Cortes y Reconexión')->sole();
        $this->actingAs($this->coordinador)->postJson($direccion, $this->datos($this->norte, $proceso))->assertForbidden();
        $this->actingAs($this->admin)->put(route('sistema.usuario.permisos', $this->coordinador),
            ['reglas' => ['flujo.capturar' => 'permitir'], 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->actingAs($this->coordinador->fresh())->postJson($direccion, $this->datos($this->sur, $proceso))
            ->assertUnprocessable()->assertJsonValidationErrors('colaborador_codigo');
        $this->post($direccion, [...$this->datos($this->norte, $proceso), 'supervisor_id' => $this->supervisor->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inspecciones', ['supervisor_id' => $this->coordinador->id, 'proceso_nombre' => $proceso->nombre]);
        $this->operador->regionesOperativas()->attach($this->norte);
        $this->regla($this->operador, 'flujo.capturar');
        $this->actingAs($this->operador->fresh())->post($direccion, $this->datos($this->norte, $proceso))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inspecciones', ['supervisor_id' => $this->operador->id]);
        $this->actingAs($this->admin)->put(route('sistema.usuario.permisos', $this->coordinador),
            ['reglas' => ['flujo.capturar' => 'denegar'], 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sistema_auditoria', ['actor_id' => $this->admin->id, 'accion' => 'permiso_usuario', 'registro' => $this->coordinador->id]);
        $this->actingAs($this->coordinador->fresh())->postJson($direccion, $this->datos($this->norte, $proceso))->assertForbidden();
        $this->actingAs($this->rrhh)->putJson(route('sistema.usuario.permisos', $this->rrhh),
            ['reglas' => ['flujo.capturar' => 'permitir'], 'current_password' => 'password'])->assertForbidden();
    }

    public function test_procesos_obligatorios_filtrables_y_visibles_en_excel_preservan_nombre_historico(): void
    {
        $cortes = ProcesoInspeccion::where('nombre', 'Cortes y Reconexión')->sole();
        $suministros = ProcesoInspeccion::where('nombre', 'Nuevos Suministros')->sole();
        $url = route('flujo.inspecciones.store');
        $this->actingAs($this->supervisor)->postJson($url, array_diff_key($this->datos($this->norte, $cortes), ['proceso_id' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('proceso_id');
        foreach ([$cortes, $suministros] as $proceso) {
            $this->post($url, $this->datos($this->norte, $proceso))->assertSessionHasNoErrors();
        }
        $this->actingAs($this->admin)->post(route('flujo.catalogos.proceso'), ['nombre' => 'Guardias QA', 'activo' => 1])->assertSessionHasNoErrors();
        $this->post(route('flujo.catalogos.proceso', $cortes), ['nombre' => 'Reconexión QA', 'activo' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procesos_inspeccion', ['nombre' => 'Guardias QA']);
        $this->actingAs($this->supervisor->fresh())->postJson($url, $this->datos($this->norte, $cortes))
            ->assertUnprocessable()->assertJsonValidationErrors('proceso_id');
        $this->get(route('flujo.inspecciones.index', ['proceso' => $cortes->id]))->assertOk()
            ->assertViewHas('inspecciones', fn ($filas) => $filas->total() === 1 && $filas->first()->proceso_nombre === 'Cortes y Reconexión');
        $archivo = $this->get(route('flujo.inspecciones.index', ['proceso' => $suministros->id, 'xlsx' => 1]))->assertDownload('inspecciones.xlsx');
        $temporal = tempnam(sys_get_temp_dir(), 'edson-xlsx-');
        try {
            file_put_contents($temporal, $archivo->streamedContent());
            $hoja = IOFactory::load($temporal)->getActiveSheet();
        } finally {
            unlink($temporal);
        }
        $this->assertSame('Proceso', (string) $hoja->getCell('L1')->getValue());
        $this->assertSame('Nuevos Suministros', (string) $hoja->getCell('L2')->getValue());
        $this->regla($this->supervisor, 'flujo.exportar_inspecciones', false);
        $this->actingAs($this->supervisor->fresh())->get(route('flujo.inspecciones.index', ['xlsx' => 1]))->assertForbidden();
    }

    public function test_corte_exige_eleccion_explicita_y_el_consolidado_conserva_proceso_y_responsable(): void
    {
        $this->actingAs($this->admin)->put(route('sistema.usuario.relaciones', $this->otroCoordinador),
            ['coordinacion' => [$this->norte->id], 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame($this->coordinador->id, $this->norte->fresh()->coordinador_id);
        $segundo = User::factory()->create(['role_id' => 3]);
        $segundo->regionesOperativas()->attach([$this->norte->id, $this->sur->id]);
        $proceso = ProcesoInspeccion::firstOrFail();
        $this->actingAs($segundo)->post(route('flujo.inspecciones.store'), $this->datos($this->norte, $proceso))->assertSessionHasNoErrors();
        $this->assertNull(Inspeccion::sole()->coordinador_id);
        $inspeccion = Inspeccion::sole();
        $corte = CorteRequerimiento::create(['nombre' => 'Corte permisos QA', 'desde' => today(), 'hasta' => today(),
            'operador_id' => $this->operador->id]);
        $corte->inspecciones()->attach($inspeccion, ['anterior' => false]);
        $resumen = app(CortesRequerimientos::class)->vistaPrevia($corte);
        $this->assertCount(2, $resumen['opciones'][$this->norte->id]);
        $this->actingAs($this->operador)->postJson(route('flujo.cortes.confirmar', $corte), ['firma' => $resumen['firma']])->assertUnprocessable();
        $url = route('flujo.cortes.coordinacion', $corte);
        $this->putJson($url, ['responsables' => [$this->norte->id => $this->rrhh->id]])->assertUnprocessable();
        $this->put($url, ['responsables' => [$this->norte->id => $this->otroCoordinador->id]])->assertSessionHasNoErrors();
        $resumen = app(CortesRequerimientos::class)->vistaPrevia($corte->fresh());
        $this->post(route('flujo.cortes.confirmar', $corte), ['firma' => $resumen['firma']])->assertSessionHasNoErrors();
        $fila = Consolidado::sole();
        $this->assertSame($this->otroCoordinador->id, $fila->coordinador_id);
        $this->assertSame($proceso->nombre, $fila->inspecciones()->sole()->proceso_nombre);
        $this->actingAs($this->coordinador)->get(route('flujo.consolidados.show', $fila))->assertNotFound();
        $this->actingAs($this->otroCoordinador)->post(route('flujo.consolidados.transicion', [$fila, 'aprobar']),
            ['version' => $fila->eventos()->max('id')])->assertSessionHasNoErrors();
        $this->assertSame($this->otroCoordinador->id, $fila->fresh()->aprobado_por);
    }

    public function test_delegacion_activa_y_vence_en_backend_con_alcance_y_auditoria(): void
    {
        $this->travelTo(now()->startOfMinute());
        $datos = $this->delegacion($this->supervisor, ['flujo.inspecciones', 'flujo.capturar'], [$this->norte->id]);
        $crear = route('sistema.usuario.delegaciones.store', $this->rrhh);
        $this->actingAs($this->admin)->postJson($crear, [...$datos, 'regiones' => [$this->sur->id]])->assertUnprocessable()->assertJsonValidationErrors('regiones');
        $this->postJson($crear, [...$datos, 'permisos' => ['sistema.campos']])->assertUnprocessable()->assertJsonValidationErrors('permisos.0');
        $this->post($crear, $datos)->assertSessionHasNoErrors();
        $fila = DelegacionOperativa::sole();
        $this->assertSame($this->admin->id, $fila->autorizada_por);
        $ruta = route('flujo.inspecciones.create');
        $this->actingAs($this->rrhh)->get($ruta)->assertForbidden();
        $this->travel(20)->minutes();
        $this->get($ruta)->assertOk()->assertSee('Norte QA')->assertDontSee('Sur QA');
        $this->getJson(route('flujo.inspecciones.colaboradores', $this->sur))->assertNotFound();
        $this->post(route('flujo.inspecciones.store'), $this->datos($this->norte, ProcesoInspeccion::firstOrFail()))->assertSessionHasNoErrors();
        $this->assertSame($this->rrhh->id, Inspeccion::sole()->supervisor_id);
        $this->assertDatabaseHas('sistema_auditoria', ['accion' => 'usar_delegacion', 'actor_id' => $this->rrhh->id]);
        $this->travelTo($fila->vigente_hasta);
        $this->get($ruta)->assertForbidden();
        $this->assertDatabaseCount('delegaciones_operativas', 1);
    }

    public function test_delegacion_de_bodega_se_limita_a_la_relacion_y_puede_revocarse(): void
    {
        $this->travelTo(now()->startOfMinute());
        $datos = $this->delegacion($this->operador, ['bodegas.consultar'], [], [$this->central->id]);
        $url = route('sistema.usuario.delegaciones.store', $this->rrhh);
        $this->actingAs($this->admin)->postJson($url, [...$datos, 'bodegas' => [$this->regional->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('bodegas');
        $this->post($url, $datos)->assertSessionHasNoErrors();
        $fila = DelegacionOperativa::sole();
        $this->travel(20)->minutes();
        $existencias = route('flujo.existencias.index');
        $this->actingAs($this->rrhh)->get($existencias)->assertOk()->assertSee('Central QA')->assertDontSee('Regional QA');
        $this->get($existencias.'?bodega='.$this->regional->id)->assertForbidden();
        $this->get($existencias.'?xlsx=1')->assertForbidden();
        $revocar = route('sistema.usuario.delegaciones.revocar', [$this->rrhh, $fila]);
        $this->patchJson($revocar, ['current_password' => 'password'])->assertForbidden();
        $this->actingAs($this->admin)->patch($revocar, ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->actingAs($this->rrhh)->get($existencias)->assertForbidden();
        $this->assertNotNull($fila->fresh()->revocada_en);
        $this->assertDatabaseHas('sistema_auditoria', ['accion' => 'revocar_delegacion', 'actor_id' => $this->admin->id]);
    }

    public function test_responsabilidad_de_central_habilita_cortes_solo_con_permiso_y_no_otorga_stock(): void
    {
        $ruta = route('flujo.cortes.create');
        $this->regla($this->coordinador, 'flujo.cortes');
        $this->actingAs($this->coordinador->fresh())->get($ruta)->assertForbidden();
        $relaciones = route('sistema.usuario.relaciones', $this->coordinador);
        $this->actingAs($this->admin)->putJson($relaciones, ['bodegas_operativas' => [$this->regional->id],
            'opera_requerimientos' => 1, 'current_password' => 'password'])->assertUnprocessable();
        $this->put($relaciones, ['bodegas_operativas' => [$this->central->id], 'opera_requerimientos' => 1,
            'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->actingAs($this->coordinador->fresh())->get($ruta)->assertOk();
        $this->get(route('flujo.importar.index'))->assertForbidden();
        $this->get(route('operador.compras.create'))->assertForbidden();
    }

    public function test_una_delegacion_conserva_coordinador_y_registra_una_aprobacion_del_sustituto(): void
    {
        $this->actingAs($this->supervisor)->post(route('flujo.inspecciones.store'),
            $this->datos($this->norte, ProcesoInspeccion::firstOrFail()))->assertSessionHasNoErrors();
        $this->actingAs($this->operador)->post(route('flujo.consolidados.store'), [
            'nombre' => 'Aprobar durante ausencia', 'desde' => today()->toDateString(), 'hasta' => today()->toDateString(),
            'inspecciones' => Inspeccion::pluck('id')->all(),
        ])->assertSessionHasNoErrors();
        $consolidado = Consolidado::sole();
        $this->travelTo(now()->startOfMinute());
        $datos = $this->delegacion($this->coordinador, ['flujo.consolidados', 'flujo.aprobar'], [$this->norte->id]);
        $this->actingAs($this->admin)->post(route('sistema.usuario.delegaciones.store', $this->rrhh), $datos)->assertSessionHasNoErrors();
        $this->regla($this->rrhh, 'flujo.consolidados');
        $this->regla($this->rrhh, 'flujo.aprobar');
        $this->rrhh->regionesOperativas()->attach($this->norte);
        $datos['titular_id'] = $this->rrhh->id;
        $this->postJson(route('sistema.usuario.delegaciones.store', $this->coordinador), $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('titular_id');
        $this->travel(20)->minutes();
        $this->actingAs($this->rrhh->fresh())->post(route('flujo.consolidados.transicion', [$consolidado, 'aprobar']),
            ['version' => $consolidado->eventos()->max('id')])->assertSessionHasNoErrors();
        $this->assertEquals($this->coordinador->id, $consolidado->fresh()->coordinador_id);
        $this->assertEquals($this->rrhh->id, $consolidado->fresh()->aprobado_por);
        $this->assertDatabaseHas('sistema_auditoria', ['actor_id' => $this->rrhh->id, 'accion' => 'usar_delegacion', 'registro' => $consolidado->id]);
    }

    private function datos(Region $region, ProcesoInspeccion $proceso): array
    {
        return ['solicitud_id' => (string) Str::uuid(), 'fecha' => today()->toDateString(), 'region_id' => $region->id,
            'colaborador_codigo' => 'COL-'.$region->id, 'proceso_id' => $proceso->id];
    }

    private function regla(User $usuario, string $permiso, bool $valor = true): void
    {
        DB::table('sistema_permisos_usuario')->insert(['user_id' => $usuario->id, 'permiso' => $permiso, 'permitido' => $valor]);
    }

    private function delegacion(User $titular, array $permisos, array $regiones, array $bodegas = []): array
    {
        return ['titular_id' => $titular->id, 'permisos' => $permisos, 'regiones' => $regiones, 'bodegas' => $bodegas,
            'vigente_desde' => now()->addMinutes(10)->format('Y-m-d\TH:i'), 'vigente_hasta' => now()->addHour()->format('Y-m-d\TH:i'),
            'motivo' => 'Cobertura de vacaciones', 'current_password' => 'password'];
    }
}
