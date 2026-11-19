<?php

use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Producto;
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
config(['inventario.seguimiento_unidades' => true]);

if (($argv[1] ?? '') === '--solicitud') {
    $solicitud = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
    $usuario = User::findOrFail($solicitud['usuario']);
    Auth::guard('web')->setUser($usuario);
    $peticion = Request::create('/operador/asignaciones', 'POST', $solicitud['datos'], server: ['HTTP_ACCEPT' => 'application/json']);
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

$resultados = ejecutarEnParalelo($colaborador, $peticiones);
$estados = array_column($resultados, 'estado');
sort($estados);
comprobar($estados === [302, 422], 'Dos bodegas deben producir una única salida. Resultados: '.json_encode($resultados));
comprobar(collect($resultados)->firstWhere('estado', 422)['errores'] === ['items'], 'La otra bodega debe quedar bloqueada por la custodia.');
$asignaciones = AsignacionInventario::where('colaborador_codigo', $colaborador->codigo)->where('producto_codigo', $producto->codigo);
$anterior = (clone $asignaciones)->custodiaActiva()->sole();
$saldo = fn (string $tipo) => (int) DB::table('inventarios')->where('producto_codigo', $producto->codigo)->where('stock_tipo', $tipo)->sum('cantidad');
comprobar($saldo('nuevo') === 3, 'La primera salida debe consumir exactamente una unidad.');

$peticion = collect($peticiones)->firstWhere('usuario', $anterior->user_id);
$peticion['datos']['solicitud_id'] = (string) Str::uuid();
$peticion['datos']['items'][0]['producto_codigo'] = strtolower($producto->codigo);
$peticion['datos']['items'][0]['resoluciones'] = [$anterior->id => ['estado_devolucion' => 'buen_estado']];
$resultados = ejecutarEnParalelo($colaborador, [$peticion, $peticion]);
$estados = array_column($resultados, 'estado');
sort($estados);
comprobar($estados === [302, 422], 'El mismo reemplazo debe aplicarse una sola vez. Resultados: '.json_encode($resultados));
comprobar(collect($resultados)->firstWhere('estado', 422)['errores'] === ['solicitud_id'], 'El reintento debe rechazarse por su identificador.');
$nueva = (clone $asignaciones)->custodiaActiva()->sole();
comprobar($nueva->producto_codigo === $producto->codigo, 'La custodia debe usar el código canónico.');
comprobar($anterior->refresh()->estado === 'Devuelta' && $nueva->asignacion_anterior_id === $anterior->id, 'La custodia debe enlazar el reemplazo.');
comprobar($saldo('nuevo') === 2 && $saldo('usado') === 1, 'Los saldos deben reflejar una devolución y una salida.');
comprobar($anterior->movimientos()->where('tipo', 'Reemplazo')->count() === 1, 'Debe existir un único movimiento de retorno.');

echo "MySQL: concurrencia entre bodegas, reintento simultáneo y saldos verificados.\n";
