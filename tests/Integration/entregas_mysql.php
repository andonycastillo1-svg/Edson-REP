<?php

use App\Models\AsignacionInventario;
use App\Models\AsignacionInventarioArchivo;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Consolidado;
use App\Models\Producto;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\InventarioStockService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (
    !$app->environment('testing') || config('database.default') !== 'mysql'
    || !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)
    || !preg_match('/^edson_[a-z0-9_]+_qa$/', (string) config('database.connections.mysql.database'))
    || config('database.connections.mysql.url')
) {
    throw new LogicException('Usa una base MySQL local y desechable edson_*_qa con APP_ENV=testing.');
}

if (($argv[1] ?? '') === '--solicitud') {
    $solicitud = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
    $usuario = User::findOrFail($solicitud['usuario']);
    Auth::guard('web')->setUser($usuario);
    $peticion = Request::create($solicitud['ruta'] ?? '/operador/asignaciones', $solicitud['metodo'] ?? 'POST', $solicitud['datos'],
        server: ['HTTP_ACCEPT' => 'application/json']);
    echo "LISTA\n";
    flush();
    $respuesta = $app->make(HttpKernel::class)->handle($peticion);
    $estado = $respuesta->getStatusCode();
    echo json_encode([
        'estado' => $estado,
        'errores' => $estado === 422 ? array_keys(json_decode($respuesta->getContent(), true)['errors'] ?? []) : [],
    ], JSON_THROW_ON_ERROR)."\n";
    exit;
}

function ejecutarEnParalelo(Colaborador $colaborador, array $solicitudes): array
{
    $procesos = [];
    DB::beginTransaction();
    try {
        Colaborador::whereKey($colaborador->codigo)->lockForUpdate()->firstOrFail();
        foreach ($solicitudes as $solicitud) {
            $proceso = new Process([PHP_BINARY, __FILE__, '--solicitud', json_encode($solicitud, JSON_THROW_ON_ERROR)]);
            $proceso->setTimeout(15);
            $proceso->start();
            $procesos[] = $proceso;
        }
        $limite = microtime(true) + 10;
        do {
            $listos = count(array_filter($procesos, fn (Process $p) => str_contains($p->getOutput(), 'LISTA')));
            if ($listos < count($procesos)) {
                usleep(10000);
            }
        } while ($listos < count($procesos) && microtime(true) < $limite);
        comprobar($listos === count($procesos), 'Los dos procesos deben estar listos antes de liberar la custodia.');
    } finally {
        DB::rollBack();
    }

    return array_map(function (Process $proceso) {
        if ($proceso->wait() !== 0) {
            throw new RuntimeException($proceso->getErrorOutput());
        }
        $lineas = explode("\n", trim($proceso->getOutput()));

        return json_decode(end($lineas), true, flags: JSON_THROW_ON_ERROR);
    }, $procesos);
}

function comprobar(bool $resultado, string $detalle): void
{
    if (!$resultado) {
        throw new RuntimeException($detalle);
    }
}

DB::table('roles')->insertOrIgnore(['id' => 2, 'nombre' => 'Almacenista']);
$identificador = strtoupper(Str::random(8));
$colaborador = Colaborador::create(['codigo' => 'C-QA-'.$identificador, 'nombre' => 'Persona QA', 'puesto' => 'Técnico', 'estado' => 'Activo']);
$producto = Producto::create(['codigo' => 'P-QA-'.$identificador, 'nombre' => 'Equipo QA', 'vida_util_meses' => 4]);
$peticiones = [];
foreach (['Norte', 'Sur'] as $region) {
    $bodega = Bodega::create(['nombre' => $region.' QA '.$identificador, 'tipo' => 'Regional']);
    $usuario = User::factory()->create(['role_id' => 2, 'bodega_id' => $bodega->id]);
    DB::transaction(fn () => app(InventarioStockService::class)->incrementar($bodega->id, $producto->codigo, 2));
    $peticiones[] = [
        'usuario' => $usuario->id,
        'datos' => [
            'solicitud_id' => (string) Str::uuid(), 'colaborador_codigo' => $colaborador->codigo,
            'fecha' => now()->toDateTimeString(), 'aprobado_por' => 'Gerencia', 'medio_solicitud' => 'Correo',
            'items' => [['producto_codigo' => $producto->codigo, 'bodega_id' => $bodega->id, 'cantidad_asignada' => 1]],
        ],
    ];
}

$asignaciones = AsignacionInventario::where('colaborador_codigo', $colaborador->codigo)->where('producto_codigo', $producto->codigo);
$saldo = fn (string $tipo) => (int) DB::table('inventarios')->where('producto_codigo', $producto->codigo)->where('stock_tipo', $tipo)->sum('cantidad');
$porUnidad = config('inventario.seguimiento_unidades');
$conciliar = function () use ($producto, $saldo, $porUnidad) {
    foreach (['nuevo', 'usado'] as $tipo) {
        $cantidad = $porUnidad
            ? DB::table('inventario_existencias')->where('producto_codigo', $producto->codigo)->where('condicion', $tipo)->count()
            : DB::table('inventario_lotes_operativos')->where('producto_codigo', $producto->codigo)->where('stock_tipo', $tipo)->sum('cantidad');
        comprobar($saldo($tipo) === (int) $cantidad, 'El saldo de '.$tipo.' debe coincidir con el detalle de las existencias.');
    }
};
$conciliar();
$resultados = ejecutarEnParalelo($colaborador, $peticiones);
comprobar(array_column($resultados, 'estado') === [302, 302], 'Ambas salidas independientes deben registrarse. Resultados: '.json_encode($resultados));
$vigente = (clone $asignaciones)->latest('id')->firstOrFail();
comprobar((clone $asignaciones)->custodiaActiva()->count() === 2, 'Las salidas adicionales deben conservar ambas custodias ordinarias.');
comprobar($vigente->asignacion_anterior_id === null && $vigente->tipo_entrega === 'adicional', 'Las salidas independientes no implican sustitución.');
comprobar($saldo('nuevo') === 2 && $saldo('usado') === 0, 'Las dos salidas no deben incorporar inventario ficticio.');
$conciliar();

$peticion = collect($peticiones)->firstWhere('usuario', $vigente->user_id);
$peticion['datos']['solicitud_id'] = (string) Str::uuid();
$peticion['datos']['items'][0]['producto_codigo'] = strtolower($producto->codigo);
$peticion['datos']['items'][0] += ['sustituir_asignacion_id' => $vigente->id, 'motivo_asignacion' => 'robo'];
$resultados = ejecutarEnParalelo($colaborador, [$peticion, $peticion]);
$estados = array_column($resultados, 'estado');
sort($estados);
comprobar($estados === [302, 422], 'El reintento de la misma salida debe aplicarse una sola vez. Resultados: '.json_encode($resultados));
comprobar(collect($resultados)->firstWhere('estado', 422)['errores'] === ['solicitud_id'], 'Debe rechazarse por su identificador.');
$nueva = (clone $asignaciones)->latest('id')->firstOrFail();
comprobar($nueva->asignacion_anterior_id === $vigente->id && $vigente->fresh()->sustituida_por_id === $nueva->id, 'Solo se vincula la sustitución explícita.');
comprobar($nueva->producto_codigo === $producto->codigo, 'La custodia debe usar el código canónico.');
comprobar($saldo('nuevo') === 1 && $saldo('usado') === 0, 'La salida debe descontar una unidad.');
comprobar((clone $asignaciones)->custodiaAbierta()->count() === 3, 'Las tres custodias deben conservar sus saldos.');
comprobar(DB::table('alertas_reemplazos_rrhh')->where('producto_codigo', $producto->codigo)->count() === 1, 'Solo el reemplazo declarado genera revisión.');
$conciliar();

DB::table('roles')->insertOrIgnore(['id' => 4, 'nombre' => 'Recursos Humanos']);
$rrhh = User::factory()->create(['role_id' => 4]);
$regionRetorno = Region::create(['nombre' => 'Devolución QA '.$identificador, 'activa' => true]);
$colaborador->update(['region_id' => $regionRetorno->id]);
$rrhh->regionesOperativas()->attach($regionRetorno);
DB::table('sistema_permisos_usuario')->insert(['user_id' => $rrhh->id, 'permiso' => 'asignaciones.devolver', 'permitido' => true]);
$devolucion = [
    'usuario' => $rrhh->id, 'ruta' => '/devoluciones/colaborador/'.$colaborador->codigo,
    'datos' => [
        'solicitud_id' => (string) Str::uuid(),
        'articulos' => (clone $asignaciones)->get()->mapWithKeys(fn ($fila) => [$fila->id => [
            'buen_estado' => (int) ($fila->id === $vigente->id), 'danado' => 0, 'sin_retorno' => (int) ($fila->id !== $vigente->id),
        ]])->all(),
    ],
];
$bodegasRetorno = (clone $asignaciones)->distinct()->pluck('bodega_id');
foreach ($bodegasRetorno as $bodegaId) {
    if ($bodegaId !== $bodegasRetorno->first()) {
        $resultado = ejecutarEnParalelo($colaborador, [$devolucion])[0];
        comprobar($resultado['estado'] === 403 && $saldo('usado') === 0, 'El alcance parcial no permite devolver.');
    }
    DB::table('bodega_usuario_operativa')->insert(['bodega_id' => $bodegaId, 'user_id' => $rrhh->id, 'opera_inventario' => true]);
}

$incompleta = $devolucion;
array_pop($incompleta['datos']['articulos']);
$resultado = ejecutarEnParalelo($colaborador, [$incompleta])[0];
comprobar($resultado['estado'] === 422 && $saldo('usado') === 0, 'La devolución incompleta debe ser atómica.');
$resultados = ejecutarEnParalelo($colaborador, [$devolucion, $devolucion]);
$estados = array_column($resultados, 'estado');
sort($estados);
comprobar($estados === [302, 422], 'La devolución concurrente solo debe aplicarse una vez. Resultados: '.json_encode($resultados));
comprobar($vigente->fresh()->estado === 'Devuelta' && $saldo('usado') === 1 && $saldo('nuevo') === 1, 'Solo un retorno real debe sumar un usado.');
comprobar(!(clone $asignaciones)->custodiaAbierta()->exists(), 'Todas las custodias deben cerrar.');
$grupoDevolucion = $vigente->movimientos()->where('tipo', 'Devolucion')->value('grupo_devolucion');
comprobar(AsignacionInventarioArchivo::where('grupo_devolucion', $grupoDevolucion)->where('tipo_documento', 'devolucion_generada')->count() === 1,
    'Debe conservarse un único PDF de la operación.');
$conciliar();
echo 'MySQL: devolución completa concurrente, PDF único y conciliación verificados por '.($porUnidad ? 'unidad' : 'lotes').".\n";

$central = app(\App\Services\BodegaAccessService::class)->principal()
    ?? Bodega::create(['nombre' => 'Central QA '.$identificador, 'tipo' => 'Principal']);
$coordinador = User::factory()->create(['role_id' => Role::where('ambito_operativo', 5)->sole()->id]);
$region = Region::create(['nombre' => 'Coordinación QA '.$identificador, 'activa' => true]);
$region->coordinador()->associate($coordinador)->save();
$colaborador->update(['region_id' => $region->id]);
$operadores = User::factory()->count(2)->create(['role_id' => 2, 'bodega_id' => $central->id]);
$consolidado = Consolidado::create(['nombre' => 'Entrega trazable QA '.$identificador, 'coordinador_id' => $coordinador->id,
    'operador_id' => $operadores->first()->id, 'desde' => today(), 'hasta' => today(), 'estado' => 'aprobado',
    'aprobado_por' => $coordinador->id, 'aprobado_en' => now()]);
$consolidado->forceFill(['detalle_individual' => true])->save();
$persona = $consolidado->participantes()->create(['colaborador_codigo' => $colaborador->codigo, 'region_id' => $region->id]);
$necesidad = $persona->necesidades()->create(['producto_codigo' => $producto->codigo, 'original' => 1, 'cantidad' => 1]);
DB::transaction(fn () => app(InventarioStockService::class)->incrementar($central->id, $producto->codigo, 2));
$solicitudes = $operadores->map(fn (User $usuario) => [
    'usuario' => $usuario->id,
    'datos' => ['solicitud_id' => (string) Str::uuid(), 'colaborador_codigo' => $colaborador->codigo,
        'consolidado_id' => $consolidado->id, 'items' => [['producto_codigo' => $producto->codigo, 'bodega_id' => $central->id]]],
])->all();
$resultados = ejecutarEnParalelo($colaborador, $solicitudes);
$estados = array_column($resultados, 'estado');
sort($estados);
comprobar($estados === [302, 422], 'Solo se debe aplicar la unidad aprobada. Resultados: '.json_encode($resultados));
comprobar(collect($resultados)->firstWhere('estado', 422)['errores'] === ['consolidado_id'], 'Debe rechazar el exceso sobre el consolidado.');
comprobar($necesidad->asignaciones()->count() === 1, 'Debe quedar una salida vinculada al requerimiento.');
comprobar((int) DB::table('inventarios')->where('producto_codigo', $producto->codigo)->where('bodega_id', $central->id)->sum('cantidad') === 1,
    'La segunda solicitud no descuenta stock.');
echo "MySQL: dos salidas concurrentes para un requerimiento aplicaron solo la unidad aprobada.\n";

foreach ([
    ['/operador/asignaciones', ['q' => $colaborador->codigo, 'por_pagina' => 25]],
    ['/operador/asignaciones', ['q' => $colaborador->codigo, 'seguimiento' => 'documentacion']],
    ['/operador/asignaciones/historial', ['desde' => today()->toDateString(), 'colaborador' => $colaborador->codigo,
        'producto' => $producto->codigo, 'tipo' => 'Devolucion', 'bodega' => $vigente->bodega_id]],
] as [$ruta, $filtros]) {
    $resultado = ejecutarEnParalelo($colaborador, [[
        'usuario' => $vigente->user_id, 'metodo' => 'GET', 'ruta' => $ruta, 'datos' => $filtros,
    ]]);
    comprobar($resultado[0]['estado'] === 200, 'El listado filtrado debe responder en MySQL. '.json_encode($resultado));
}
echo "MySQL: filtros de asignaciones, hojas pendientes e historial verificados.\n";
