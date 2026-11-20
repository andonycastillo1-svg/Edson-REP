<?php

use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\ImportacionAsignacion;
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

if (!$app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.url')
    || !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)
    || !preg_match('/^edson_[a-z0-9_]+_qa$/', (string) config('database.connections.mysql.database'))) {
    throw new LogicException('Usa MySQL local y una base desechable edson_*_qa con APP_ENV=testing.');
}

if (($argv[1] ?? '') === '--confirmar') {
    Auth::guard('web')->setUser(User::findOrFail($argv[2]));
    echo "LISTA\n";
    flush();
    $peticion = Request::create('/flujo/asignaciones/importar/'.$argv[3], 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    $respuesta = $app->make(HttpKernel::class)->handle($peticion);
    $estado = $respuesta->getStatusCode();
    echo json_encode([
        'estado' => $estado,
        'errores' => $estado === 422 ? array_keys(json_decode($respuesta->getContent(), true)['errors'] ?? []) : [],
    ], JSON_THROW_ON_ERROR)."\n";
    exit;
}

function comprobar(bool $resultado, string $detalle): void
{
    if (!$resultado) {
        throw new RuntimeException($detalle);
    }
}

DB::table('roles')->insertOrIgnore(['id' => 2, 'nombre' => 'Operador']);
$bodega = Bodega::where('tipo', 'Principal')->orderBy('id')->first()
    ?? Bodega::create(['nombre' => 'Central flujo QA', 'tipo' => 'Principal']);
$codigo = 'QA-'.strtoupper(Str::random(8));
$colaborador = Colaborador::create(['codigo' => 'C-'.$codigo, 'nombre' => 'Entrega QA', 'puesto' => 'Soporte', 'estado' => 'Activo']);
$productos = collect([1, 2])->map(fn ($i) => Producto::create(['codigo' => $codigo.'-'.$i, 'nombre' => 'Equipo QA '.$i, 'vida_util_meses' => 6]));
DB::transaction(function () use ($bodega, $productos) {
    foreach ($productos as $producto) {
        app(InventarioStockService::class)->incrementar($bodega->id, $producto->codigo, 4, 50);
    }
});
$filas = $productos->mapWithKeys(fn ($producto, $i) => [$i + 2 => [
    'colaborador_codigo' => $colaborador->codigo, 'producto_codigo' => $producto->codigo, 'cantidad' => 1,
    'fecha' => today()->toDateString(), 'aprobado_por' => null, 'medio_solicitud' => null,
    'motivo_asignacion' => null, 'observaciones' => null,
]])->all();
$lotes = collect([1, 2])->map(function () use ($bodega, $filas) {
    $usuario = User::factory()->create(['role_id' => 2, 'bodega_id' => $bodega->id]);

    return ImportacionAsignacion::create([
        'id' => (string) Str::uuid(), 'user_id' => $usuario->id, 'bodega_id' => $bodega->id, 'filas' => $filas,
    ]);
});

DB::beginTransaction();
try {
    Colaborador::whereKey($colaborador->codigo)->lockForUpdate()->firstOrFail();
    $procesos = $lotes->map(function (ImportacionAsignacion $lote) {
        $proceso = new Process([PHP_BINARY, __FILE__, '--confirmar', (string) $lote->user_id, $lote->id]);
        $proceso->setTimeout(15);
        $proceso->start();

        return $proceso;
    });
    $limite = microtime(true) + 10;
    while (!$procesos->every(fn (Process $p) => str_contains($p->getOutput(), 'LISTA')) && microtime(true) < $limite) {
        usleep(10000);
    }
    comprobar($procesos->every(fn (Process $p) => str_contains($p->getOutput(), 'LISTA')), 'Los dos procesos deben estar listos.');
} finally {
    DB::rollBack();
}

$resultados = $procesos->map(function (Process $proceso) {
    comprobar($proceso->wait() === 0, $proceso->getErrorOutput());
    $lineas = explode("\n", trim($proceso->getOutput()));

    return json_decode(end($lineas), true, flags: JSON_THROW_ON_ERROR);
});
comprobar($resultados->pluck('estado')->sort()->values()->all() === [302, 422], 'Las confirmaciones concurren sin duplicar el lote: '.$resultados->toJson());
comprobar($resultados->firstWhere('estado', 422)['errores'] === ['excel'], 'La huella protege el mismo lote entre cuentas.');
$asignaciones = AsignacionInventario::where('colaborador_codigo', $colaborador->codigo)->get();
comprobar($asignaciones->count() === 2 && $asignaciones->pluck('grupo_asignacion')->unique()->count() === 1, 'Debe existir una entrega con dos productos.');
foreach ($productos as $producto) {
    $saldo = DB::table('inventarios')->where('producto_codigo', $producto->codigo)->where('bodega_id', $bodega->id)->sum('cantidad');
    comprobar((int) $saldo === 3, 'Cada producto debe descontarse exactamente una vez.');
}
$registrado = ImportacionAsignacion::whereKey($lotes->pluck('id')->all())->whereNotNull('confirmada_en')->sole();
comprobar(count($registrado->entregas) === 1, 'La importación genera una sola hoja por colaborador.');

echo 'MySQL: dos operadores confirmaron el mismo contenido en paralelo. Un lote aplicado, saldos correctos y una hoja por colaborador.'."\n";
