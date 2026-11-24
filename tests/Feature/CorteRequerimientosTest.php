<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Consolidado;
use App\Models\CorteRequerimiento;
use App\Models\Inspeccion;
use App\Models\Producto;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class CorteRequerimientosTest extends TestCase
{
    use RefreshDatabase;

    private User $central;
    private User $supervisor;
    private User $coordinador;
    private User $otroCoordinador;
    private User $regional;
    private Region $norte;
    private Region $centro;
    private Region $sur;
    private Region $sinResponsable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Administrador'],
            ['id' => 2, 'nombre' => 'Almacenista'],
            ['id' => 3, 'nombre' => 'Supervisor'],
        ]);
        $ambito = Role::where('ambito_operativo', 5)->sole();
        $principal = Bodega::create(['nombre' => 'Central', 'tipo' => 'Principal']);
        $secundaria = Bodega::create(['nombre' => 'Sucursal', 'tipo' => 'Regional']);
        $this->central = User::factory()->create(['role_id' => 2, 'bodega_id' => $principal->id]);
        $this->regional = User::factory()->create(['role_id' => 2, 'bodega_id' => $secundaria->id]);
        $this->supervisor = User::factory()->create(['role_id' => 3]);
        $this->coordinador = User::factory()->create(['role_id' => $ambito->id, 'name' => 'Coordinación Norte y Centro']);
        $this->otroCoordinador = User::factory()->create(['role_id' => $ambito->id, 'name' => 'Coordinación Sur']);
        $this->norte = $this->crearRegion('Norte', $this->coordinador);
        $this->centro = $this->crearRegion('Centro', $this->coordinador);
        $this->sur = $this->crearRegion('Sur', $this->otroCoordinador);
        $this->sinResponsable = $this->crearRegion('Occidente');
        Producto::create(['codigo' => 'EPP-01', 'nombre' => 'Casco de prueba', 'vida_util_meses' => 12]);
    }

    public function test_genera_un_consolidado_por_coordinador_y_conserva_las_inspecciones_originales(): void
    {
        $norte = $this->inspeccion($this->norte, 5);
        $centro = $this->inspeccion($this->centro, 3, 3);
        $sur = $this->inspeccion($this->sur, 2);
        $anterior = $this->inspeccion($this->centro, 12);
        $omitir = $this->inspeccion($this->norte, 13);
        $posterior = $this->inspeccion($this->sur, 1);

        $this->actingAs($this->central);
        $this->get(route('flujo.inspecciones.index'))->assertOk()->assertSee('Nuevo corte')->assertSee('Historial de cortes');
        $corte = $this->crearCorte();
        $this->get(route('flujo.cortes.seleccion', $corte))->assertOk()->assertSee('Inspecciones anteriores pendientes')
            ->assertSee($anterior->colaborador->nombre)->assertDontSee($posterior->colaborador->nombre);
        $this->seleccionar($corte, [$norte, $centro, $sur], [$anterior], [$omitir]);
        $resumen = $this->resumen($corte);
        $this->assertSame(4, $resumen['total']);
        $this->assertSame(4, $resumen['colaboradores']);
        $this->assertSame(3, $resumen['regiones']);
        $this->assertSame(2, $resumen['coordinadores']);
        $this->assertSame(1, $resumen['anteriores']);
        $this->assertDatabaseCount('consolidados', 0);
        $url = route('flujo.cortes.confirmar', $corte);
        $this->post($url, ['firma' => $resumen['firma']])->assertRedirect(route('flujo.cortes.show', $corte));
        $this->post($url, ['firma' => $resumen['firma']])->assertRedirect();
        $this->assertSame('confirmado', $corte->fresh()->estado);
        $this->assertSame($this->central->id, $corte->operador_id);
        $this->assertDatabaseCount('consolidados', 2);
        $this->assertDatabaseCount('consolidado_inspeccion', 4);
        $this->assertDatabaseHas('corte_inspeccion', ['corte_id' => $corte->id, 'inspeccion_id' => $anterior->id, 'anterior' => true]);
        $this->assertFalse($omitir->consolidados()->exists());
        $this->assertFalse($posterior->consolidados()->exists());
        $this->assertSame(3, $centro->lineas()->sole()->cantidad);
        $this->assertDatabaseCount('inventarios', 0);
        $this->assertDatabaseCount('movimientos', 0);

        $principal = $corte->consolidados()->where('coordinador_id', $this->coordinador->id)->sole();
        $secundario = $corte->consolidados()->where('coordinador_id', $this->otroCoordinador->id)->sole();
        $this->assertEqualsCanonicalizing([$norte->id, $centro->id, $anterior->id], $principal->inspecciones()->pluck('inspecciones.id')->all());
        $this->assertEqualsCanonicalizing([$sur->id], $secundario->inspecciones()->pluck('inspecciones.id')->all());
        $this->assertSame($corte->desde->toDateString(), $principal->desde->toDateString());
        $this->assertDatabaseHas('sistema_auditoria', ['actor_id' => $this->central->id, 'registro' => (string) $corte->id, 'accion' => 'confirmar_corte']);
        $this->get(route('flujo.cortes.index'))->assertOk()->assertSee($corte->nombre);
        $this->get(route('flujo.cortes.show', $corte))->assertOk()->assertSee('Anterior al período')->assertSee($principal->nombre);
        $this->get(route('flujo.consolidados.show', $principal))->assertOk()->assertSee('Corte de origen');

        $this->actingAs($this->coordinador)->get(route('flujo.cortes.show', $corte))->assertForbidden();
        $this->get(route('flujo.consolidados.show', $secundario))->assertNotFound();
        $this->get(route('flujo.consolidados.show', $principal))->assertOk()->assertDontSee('Corte de origen');
        $this->post(route('flujo.consolidados.transicion', [$principal, 'aprobar']), ['version' => $principal->eventos()->max('id')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('aprobado', $principal->fresh()->estado);
    }

    public function test_periodos_libres_y_clasificacion_explicita_de_inspecciones_fuera_del_periodo(): void
    {
        $anterior = $this->inspeccion($this->centro, 8);
        $dentro = $this->inspeccion($this->centro, 3);
        $despues = $this->inspeccion($this->norte, 1);
        $this->actingAs($this->central);
        $ruta = route('flujo.cortes.store');
        $this->postJson($ruta, ['nombre' => 'Fechas invertidas', 'desde' => $this->fecha(2), 'hasta' => $this->fecha(5)])
            ->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $corte = $this->crearCorte('Corte extraordinario', 3, 3);
        $seleccion = route('flujo.cortes.seleccion.guardar', $corte);
        foreach ([
            ['visibles' => [$anterior->id], 'dentro' => [$anterior->id]],
            ['visibles' => [$dentro->id], 'antiguas' => [$dentro->id]],
            ['visibles' => [$despues->id], 'dentro' => [$despues->id]],
            ['visibles' => [$dentro->id], 'dentro' => [$dentro->id], 'antiguas' => [$dentro->id]],
            ['visibles' => [], 'dentro' => [$dentro->id]],
        ] as $datos) {
            $this->putJson($seleccion, $datos)->assertUnprocessable()->assertJsonValidationErrors('inspecciones');
        }
        $this->assertSame(0, $corte->inspecciones()->count());
        $this->seleccionar($corte, [$dentro], [], [$anterior]);
        $firma = $this->resumen($corte)['firma'];
        $this->post(route('flujo.cortes.confirmar', $corte), compact('firma'))->assertRedirect()->assertSessionHasNoErrors();
        $siguiente = $this->crearCorte('Corte siguiente', 1, 0);
        $this->seleccionar($siguiente, [$despues], [$anterior]);
        $this->assertSame(1, $this->resumen($siguiente)['anteriores']);
        $this->assertFalse($anterior->consolidados()->exists());
        $this->assertFalse($despues->consolidados()->exists());
    }

    public function test_avisa_si_falta_coordinacion_y_exige_revisar_un_cambio_de_responsable(): void
    {
        $sinResponsable = $this->inspeccion($this->sinResponsable, 3);
        $this->actingAs($this->central);
        $corte = $this->crearCorte();
        $this->seleccionar($corte, [$sinResponsable]);
        $resumen = $this->resumen($corte);
        $this->assertStringContainsString('Occidente', $resumen['alertas'][0]);
        $confirmar = route('flujo.cortes.confirmar', $corte);
        $this->postJson($confirmar, ['firma' => $resumen['firma']])->assertUnprocessable()->assertJsonValidationErrors('inspecciones');
        $this->assertDatabaseCount('consolidados', 0);

        $this->sinResponsable->coordinador()->associate($this->coordinador)->save();
        $firmaAnterior = $this->resumen($corte)['firma'];
        $this->sinResponsable->coordinador()->associate($this->otroCoordinador)->save();
        $this->postJson($confirmar, ['firma' => $firmaAnterior])->assertUnprocessable()
            ->assertJsonPath('errors.inspecciones.0', fn ($texto) => str_contains($texto, 'coordinación cambió'));
        $this->assertDatabaseCount('consolidados', 0);

        $this->post($confirmar, ['firma' => $this->resumen($corte)['firma']])->assertRedirect();
        $this->assertEquals($this->otroCoordinador->id, $corte->consolidados()->sole()->coordinador_id);
    }

    public function test_cancelar_borrador_conserva_su_historial_y_no_reserva_las_inspecciones(): void
    {
        $fila = $this->inspeccion($this->centro, 4);
        $this->actingAs($this->central);
        $corte = $this->crearCorte('Borrador cancelado');
        $this->seleccionar($corte, [$fila]);
        $this->post(route('flujo.cortes.cancelar', $corte))->assertRedirect(route('flujo.cortes.show', $corte));
        $this->get(route('flujo.cortes.show', $corte))->assertOk()->assertSee('Cancelado')->assertSee($fila->colaborador->nombre);
        $this->assertDatabaseHas('corte_inspeccion', ['corte_id' => $corte->id, 'inspeccion_id' => $fila->id]);
        $this->assertDatabaseCount('consolidado_inspeccion', 0);
        $nuevo = $this->crearCorte('Segundo intento');
        $this->seleccionar($nuevo, [$fila]);
        $this->post(route('flujo.cortes.confirmar', $nuevo), ['firma' => $this->resumen($nuevo)['firma']])->assertRedirect();
        $this->postJson(route('flujo.cortes.cancelar', $nuevo))->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertDatabaseCount('consolidado_inspeccion', 1);
    }

    public function test_bloquea_conflictos_con_cortes_y_consolidados_anteriores(): void
    {
        $fila = $this->inspeccion($this->norte, 3);
        $this->actingAs($this->central);
        $primero = $this->crearCorte('Primero');
        $segundo = $this->crearCorte('Segundo');
        $this->seleccionar($primero, [$fila]);
        $this->seleccionar($segundo, [$fila]);
        $firma = $this->resumen($segundo)['firma'];
        $this->post(route('flujo.consolidados.store'), [
            'nombre' => 'Consolidado histórico', 'desde' => $this->fecha(4), 'hasta' => $this->fecha(2), 'inspecciones' => [$fila->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(Consolidado::sole()->corte_id);
        $this->postJson(route('flujo.cortes.confirmar', $segundo), compact('firma'))->assertUnprocessable()->assertJsonValidationErrors('inspecciones');
        $this->assertStringContainsString('Ya se consolidaron', $this->resumen($primero)['alertas'][0]);
        $this->get(route('flujo.cortes.seleccion', $primero))->assertOk()
            ->assertViewHas('pendientes', ['periodo' => 0, 'anteriores' => 0])
            ->assertSee('Ya consolidada. Desmarca esta inspección.');
        $this->seleccionar($primero, [], [], [$fila]);
        $this->assertDatabaseCount('consolidados', 1);
        $this->assertSame(0, $primero->inspecciones()->count());
    }

    public function test_paginacion_guarda_la_seleccion_de_paginas_anteriores(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->inspeccion($this->norte, 3);
        }
        $this->actingAs($this->central);
        $corte = $this->crearCorte();
        $filas = Inspeccion::latest('id')->get();
        $url = route('flujo.cortes.seleccion.guardar', $corte);
        $this->put($url, ['visibles' => $filas->take(20)->modelKeys(), 'dentro' => [$filas->first()->id], 'periodo' => 2])
            ->assertRedirect(route('flujo.cortes.seleccion', ['corte' => $corte, 'periodo' => 2, 'anteriores' => 1]));
        $this->seleccionar($corte, [$filas->last()]);
        $this->assertEqualsCanonicalizing([$filas->first()->id, $filas->last()->id], $corte->inspecciones()->pluck('inspecciones.id')->all());
    }

    public function test_si_un_grupo_falla_no_se_genera_ningun_consolidado_del_corte(): void
    {
        $primera = $this->inspeccion($this->norte, 3);
        $segunda = $this->inspeccion($this->sur, 3, PHP_INT_MAX);
        $tercera = $this->inspeccion($this->sur, 4, 1, $segunda->colaborador);
        $this->actingAs($this->central);
        $corte = $this->crearCorte();
        $this->seleccionar($corte, [$primera, $segunda, $tercera]);
        $this->postJson(route('flujo.cortes.confirmar', $corte), ['firma' => $this->resumen($corte)['firma']])
            ->assertUnprocessable()->assertJsonValidationErrors('inspecciones');
        $this->assertDatabaseCount('consolidados', 0);
        $this->assertDatabaseCount('consolidado_inspeccion', 0);
        $this->assertSame('borrador', $corte->fresh()->estado);
    }

    public function test_el_borrador_es_del_operador_y_las_rutas_respetan_permisos(): void
    {
        $this->actingAs($this->central);
        $corte = $this->crearCorte();
        $otro = User::factory()->create(['role_id' => 2, 'bodega_id' => $this->central->bodega_id]);
        $this->actingAs($otro)->get(route('flujo.cortes.show', $corte))->assertNotFound();
        $this->putJson(route('flujo.cortes.seleccion.guardar', $corte), [])->assertNotFound();
        foreach ([$this->supervisor, $this->regional, $this->coordinador] as $usuario) {
            $this->actingAs($usuario)->get(route('flujo.cortes.index'))->assertForbidden();
            $this->get(route('flujo.cortes.create'))->assertForbidden();
            $this->postJson(route('flujo.cortes.confirmar', $corte), ['firma' => str_repeat('0', 64)])->assertForbidden();
        }
        DB::table('sistema_permisos_usuario')->insert(['user_id' => $this->central->id, 'permiso' => 'flujo.inspecciones', 'permitido' => false]);
        $this->actingAs($this->central->fresh())->get(route('flujo.cortes.create'))->assertForbidden();
    }

    private function crearRegion(string $nombre, ?User $coordinador = null): Region
    {
        $region = Region::create(['nombre' => $nombre, 'activa' => true]);
        if ($coordinador) {
            $region->coordinador()->associate($coordinador)->save();
        }

        return $region;
    }

    private function inspeccion(Region $region, int $dias, int $cantidad = 1, ?Colaborador $persona = null): Inspeccion
    {
        $codigo = 'TEC-'.Str::upper(Str::random(8));
        $persona ??= Colaborador::create([
            'codigo' => $codigo, 'nombre' => 'Persona '.$codigo, 'estado' => 'Activo',
            'puesto' => 'Técnico', 'region_id' => $region->id,
        ]);
        $fila = Inspeccion::create([
            'supervisor_id' => $this->supervisor->id, 'region_id' => $region->id,
            'colaborador_codigo' => $persona->codigo, 'puesto' => 'Técnico',
            'fecha' => $this->fecha($dias), 'solicitud_id' => (string) Str::uuid(),
        ]);
        $fila->lineas()->create(['producto_codigo' => 'EPP-01', 'etiqueta' => 'Casco', 'cantidad' => $cantidad]);

        return $fila;
    }

    private function crearCorte(string $nombre = 'Corte de prueba', int $desde = 6, int $hasta = 2): CorteRequerimiento
    {
        $this->post(route('flujo.cortes.store'), ['nombre' => $nombre, 'desde' => $this->fecha($desde), 'hasta' => $this->fecha($hasta)])
            ->assertRedirect()->assertSessionHasNoErrors();

        return CorteRequerimiento::where('nombre', $nombre)->sole();
    }

    private function seleccionar(CorteRequerimiento $corte, array $dentro, array $antiguas = [], array $sobra = []): void
    {
        $this->put(route('flujo.cortes.seleccion.guardar', $corte), [
            'visibles' => collect([...$dentro, ...$antiguas, ...$sobra])->pluck('id')->all(),
            'dentro' => collect($dentro)->pluck('id')->all(), 'antiguas' => collect($antiguas)->pluck('id')->all(),
        ])->assertRedirect(route('flujo.cortes.show', $corte))->assertSessionHasNoErrors();
    }

    private function resumen(CorteRequerimiento $corte): array
    {
        return $this->get(route('flujo.cortes.show', $corte))->assertOk()->viewData('resumen');
    }

    private function fecha(int $dias): string
    {
        return today()->subDays($dias)->toDateString();
    }
}
