<?php

namespace Tests\Feature;

use App\Models\AsignacionInventario;
use App\Models\InventarioExistencia;
use App\Services\InventarioLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventarioLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventarioLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventarioLifecycleService::class);
        DB::table('roles')->insert(['id' => 1, 'nombre' => 'Administrador']);
        DB::table('users')->insert(['id' => 1, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'x', 'role_id' => 1]);
        DB::table('bodegas')->insert([['id' => 1, 'nombre' => 'Principal', 'tipo' => 'Principal'], ['id' => 2, 'nombre' => 'Retorno', 'tipo' => 'Regional']]);
        DB::table('colaboradores')->insert(['codigo' => 'C1', 'nombre' => 'Persona', 'puesto' => 'Pruebas', 'estado' => 'Activo']);
        $this->actingAs(\App\Models\User::find(1));
    }

    private function producto(string $codigo, ?int $meses): void
    {
        DB::table('productos')->insert(['codigo' => $codigo, 'nombre' => $codigo, 'vida_util_meses' => $meses]);
    }

    private function asignacion(string $codigo, int $cantidad, Carbon $fecha, string $tipo = 'nuevo'): AsignacionInventario
    {
        return AsignacionInventario::create([
            'colaborador_codigo' => 'C1', 'producto_codigo' => $codigo, 'bodega_id' => 1,
            'user_id' => 1, 'cantidad_asignada' => $cantidad, 'fecha' => $fecha,
            'estado' => 'Activa', 'stock_tipo' => $tipo, 'costo_unitario' => 100,
        ]);
    }

    public function test_vida_se_consume_solo_en_periodos_y_se_pausa_al_devolver(): void
    {
        $this->producto('P1', 12);
        $this->service->crearNuevas('P1', 1, 1, 100);
        $inicio = Carbon::parse('2026-01-01 08:00:00');
        $asignacion = $this->asignacion('P1', 1, $inicio);
        $this->service->reservar($asignacion, 'nuevo', 1, $inicio);
        $this->service->devolver($asignacion, 1, 'buen_estado', 2, 'Cambio', null, $inicio->copy()->addDays(5));

        $existencia = InventarioExistencia::first();
        $this->assertSame('usado', $existencia->condicion);
        $this->assertSame(2, $existencia->bodega_id);
        $this->assertSame(12 * 30 * 86400 - 5 * 86400, $existencia->vida_util_restante_segundos);
        Carbon::setTestNow($inicio->copy()->addYear());
        $this->assertSame(12 * 30 * 86400 - 5 * 86400, $existencia->fresh()->vida_util_restante_segundos);
        Carbon::setTestNow();
    }

    public function test_usado_con_menor_vida_se_asigna_primero_y_cero_es_reasignable(): void
    {
        $this->producto('P2', 5);
        foreach ([3 * 30 * 86400, 0, 30 * 86400] as $vida) {
            InventarioExistencia::create(['producto_codigo' => 'P2', 'bodega_id' => 1, 'condicion' => 'usado', 'vida_util_inicial_segundos' => 5 * 30 * 86400, 'vida_util_restante_segundos' => $vida]);
        }
        $asignacion = $this->asignacion('P2', 1, Carbon::parse('2026-01-01'), 'usado');
        $reservadas = $this->service->reservar($asignacion, 'usado', 1, Carbon::parse('2026-01-01'));
        $this->assertSame(0, $reservadas->first()->vida_util_restante_segundos);

        $periodos = $this->service->devolver($asignacion, 1, 'danado', 1, 'Daño', null, Carbon::parse('2026-01-02'));
        $this->assertSame(0, $periodos->first()->fresh()->vida_util_restante_segundos);
        $this->assertSame('no_aplica', $periodos->first()->fresh()->estado_cobro);
    }

    public function test_producto_sin_vida_util_permanece_no_aplicable(): void
    {
        $this->producto('P3', null);
        $this->service->crearNuevas('P3', 1, 1);
        $asignacion = $this->asignacion('P3', 1, Carbon::parse('2026-01-01'));
        $this->service->reservar($asignacion, 'nuevo', 1, Carbon::parse('2026-01-01'));
        $periodo = $this->service->devolver($asignacion, 1, 'buen_estado', 1, 'Cambio', null, Carbon::parse('2026-07-01'))->first()->fresh();
        $this->assertNull($periodo->vida_util_al_asignar_segundos);
        $this->assertNull($periodo->vida_util_restante_segundos);
    }

    public function test_devolucion_parcial_no_duplica_y_no_permite_devolver_dos_veces(): void
    {
        $this->producto('P4', 1);
        $this->service->crearNuevas('P4', 1, 2);
        $asignacion = $this->asignacion('P4', 2, Carbon::parse('2026-01-01'));
        $this->service->reservar($asignacion, 'nuevo', 2, Carbon::parse('2026-01-01'));
        $this->service->devolver($asignacion, 1, 'buen_estado', 1, 'Parcial', null, Carbon::parse('2026-01-02'));
        $this->assertSame(1, InventarioExistencia::where('condicion', 'usado')->count());
        $this->assertSame(1, InventarioExistencia::where('condicion', 'asignado')->count());
        $this->service->devolver($asignacion, 1, 'perdido', 1, 'No devuelto', null, Carbon::parse('2026-01-03'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->devolver($asignacion, 1, 'buen_estado', 1, 'Duplicada', null, Carbon::parse('2026-01-04'));
    }
}
