<?php

namespace Tests\Feature;

use App\Models\AsignacionInventario;
use App\Models\User;
use App\Services\AsignacionReplacementService;
use App\Services\InventarioLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AsignacionReplacementServiceTest extends TestCase
{
    use RefreshDatabase;

    private AsignacionReplacementService $service;
    private User $admin;
    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AsignacionReplacementService::class);
        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Administrador'],
            ['id' => 2, 'nombre' => 'Encargado'],
        ]);
        DB::table('bodegas')->insert(['id' => 1, 'nombre' => 'Principal', 'tipo' => 'Principal']);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@local.test', 'password' => 'x', 'role_id' => 1],
            ['id' => 2, 'name' => 'Operador', 'email' => 'operador@local.test', 'password' => 'x', 'role_id' => 2, 'bodega_id' => 1],
        ]);
        DB::table('colaboradores')->insert([
            ['codigo' => 'C1', 'nombre' => 'Técnico uno', 'estado' => 'Activo'],
            ['codigo' => 'C2', 'nombre' => 'Técnico dos', 'estado' => 'Activo'],
        ]);
        DB::table('productos')->insert(['codigo' => 'P1', 'nombre' => 'Guantes', 'vida_util_meses' => 4]);
        DB::table('inventarios')->insert(['producto_codigo' => 'P1', 'bodega_id' => 1, 'cantidad' => 5, 'stock_tipo' => 'nuevo']);
        $this->admin = User::findOrFail(1);
        $this->operador = User::findOrFail(2);
    }

    public function test_asignacion_inicial_no_guarda_datos_de_reposicion(): void
    {
        $datos = $this->service->clasificarYValidar($this->admin, $this->item(), 'C1');
        $this->assertSame('inicial', $datos['tipo_entrega']);
        $this->assertNull($datos['asignacion_anterior_id']);
        $this->assertNull($datos['motivo_reposicion']);
    }

    public function test_reposicion_normal_cuando_la_vida_finalizo(): void
    {
        $anterior = $this->asignacion('C1', 0);
        $datos = $this->service->clasificarYValidar($this->admin, $this->item(['asignacion_anterior_id' => $anterior->id]), 'C1');
        $this->assertSame('reposicion_normal', $datos['tipo_entrega']);
        $this->assertSame(0, $datos['vida_restante_anterior_segundos']);
    }

    public function test_reposicion_anticipada_conserva_motivo_y_vida_detectada(): void
    {
        $anterior = $this->asignacion('C1', 3);
        $datos = $this->service->clasificarYValidar($this->admin, $this->item([
            'asignacion_anterior_id' => $anterior->id,
            'solicitado_por' => 'Supervisor de turno',
            'motivo_reposicion' => 'desgaste_prematuro',
            'justificacion_reposicion' => 'El material perdió adherencia.',
        ]), 'C1');
        $this->assertSame('reposicion_anticipada', $datos['tipo_entrega']);
        $this->assertSame('desgaste_prematuro', $datos['motivo_reposicion']);
        $this->assertGreaterThan(0, $datos['vida_restante_anterior_segundos']);
    }

    public function test_reposicion_anticipada_sin_justificacion_falla(): void
    {
        $anterior = $this->asignacion('C1', 3);
        $this->expectException(ValidationException::class);
        $this->service->clasificarYValidar($this->admin, $this->item([
            'asignacion_anterior_id' => $anterior->id,
            'solicitado_por' => 'Supervisor',
            'motivo_reposicion' => 'otro',
        ]), 'C1');
    }

    public function test_entrega_adicional_no_modifica_asignacion_anterior_ni_stock(): void
    {
        $anterior = $this->asignacion('C1', 3);
        $stockAntes = DB::table('inventarios')->value('cantidad');
        $datos = $this->service->clasificarYValidar($this->admin, $this->item([
            'asignacion_anterior_id' => $anterior->id,
            'modo_entrega' => 'adicional',
            'justificacion_reposicion' => 'Segundo par para otra tarea.',
        ]), 'C1');
        $this->assertSame('adicional', $datos['tipo_entrega']);
        $this->assertSame('Activa', $anterior->fresh()->estado);
        $this->assertSame($stockAntes, DB::table('inventarios')->value('cantidad'));
    }

    public function test_no_permite_relacionar_asignacion_de_otro_colaborador(): void
    {
        $ajena = $this->asignacion('C2', 3);
        $this->expectException(ValidationException::class);
        $this->service->clasificarYValidar($this->admin, $this->item(['asignacion_anterior_id' => $ajena->id]), 'C1');
    }

    public function test_asignacion_devuelta_no_se_considera_activa(): void
    {
        $this->asignacion('C1', 3, 'Devuelta');
        $datos = $this->service->clasificarYValidar($this->admin, $this->item(), 'C1');
        $this->assertSame('inicial', $datos['tipo_entrega']);
    }

    public function test_endpoint_respeta_acceso_de_admin_y_operador(): void
    {
        $this->asignacion('C1', 3, 'Activa', 2);
        $consulta = ['colaborador_codigo' => 'C1', 'producto_codigo' => 'P1'];
        $this->actingAs($this->admin)->getJson(route('admin.asignaciones.activas_producto', $consulta))->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->operador)->getJson(route('operador.asignaciones.activas_producto', $consulta))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_reserva_sigue_rechazando_stock_interno_insuficiente(): void
    {
        $asignacion = $this->asignacion('C1', 3);
        $this->actingAs($this->admin);
        $this->expectException(ValidationException::class);
        app(InventarioLifecycleService::class)->reservar($asignacion, 'nuevo', 1, now());
    }

    private function asignacion(string $colaborador, int $vidaRestante, string $estado = 'Activa', int $userId = 1): AsignacionInventario
    {
        return AsignacionInventario::create([
            'colaborador_codigo' => $colaborador,
            'producto_codigo' => 'P1',
            'bodega_id' => 1,
            'user_id' => $userId,
            'cantidad_asignada' => 1,
            'fecha' => now()->subDays(2),
            'estado' => $estado,
            'stock_tipo' => 'nuevo',
            'vida_util_original_meses' => 4,
            'vida_util_restante_meses' => $vidaRestante,
        ]);
    }

    private function item(array $extra = []): array
    {
        return array_merge([
            'producto_codigo' => 'P1',
            'bodega_id' => 1,
            'cantidad_asignada' => 1,
            'stock_tipo' => 'nuevo',
            'modo_entrega' => 'reposicion',
        ], $extra);
    }
}
