<?php

use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\CorteRequerimiento;
use App\Models\Inspeccion;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\BodegaAccessService;
use App\Services\CortesRequerimientos;
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
if (!$app->environment('testing') || config('database.default') !== 'mysql'
    || !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)
    || !preg_match('/^edson_[a-z0-9_]+_qa$/', (string) config('database.connections.mysql.database'))
    || config('database.connections.mysql.url')) {
    throw new LogicException('Usa una base MySQL local y desechable edson_*_qa con APP_ENV=testing.');
}

if (($argv[1] ?? '') === '--confirmar') {
    $peticion = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
    Auth::guard('web')->setUser(User::findOrFail($peticion['usuario']));
    echo "LISTO\n";
    flush();
    $respuesta = $app->make(HttpKernel::class)->handle(Request::create($peticion['ruta'], 'POST', $peticion['datos'],
        server: ['HTTP_ACCEPT' => 'application/json']));
    echo json_encode(['estado' => $respuesta->getStatusCode(), 'contenido' => $respuesta->getContent()], JSON_THROW_ON_ERROR)."\n";
    exit;
}

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function confirmarEnParalelo(Inspeccion $inspeccion, array $peticiones): void
{
    $procesos = [];
    DB::beginTransaction();
    try {
        Inspeccion::whereKey($inspeccion->id)->lockForUpdate()->firstOrFail();
        foreach ($peticiones as $peticion) {
            $proceso = new Process([PHP_BINARY, __FILE__, '--confirmar', json_encode($peticion, JSON_THROW_ON_ERROR)]);
            $proceso->setTimeout(15)->start();
            $procesos[] = $proceso;
        }
        $limite = microtime(true) + 8;
        do {
            $listos = collect($procesos)->every(fn ($p) => str_contains($p->getOutput(), 'LISTO'));
            if (!$listos) {
                usleep(10000);
            }
        } while (!$listos && microtime(true) < $limite);
        comprobar($listos, 'Ambas confirmaciones deben quedar listas para competir por la inspección.');
    } finally {
        DB::rollBack();
    }
    $resultados = collect($procesos)->map(function (Process $proceso) {
        comprobar($proceso->wait() === 0, $proceso->getErrorOutput());
        $lineas = explode("\n", trim($proceso->getOutput()));

        return json_decode(end($lineas), true, flags: JSON_THROW_ON_ERROR);
    });
    comprobar($resultados->pluck('estado')->sort()->values()->all() === [302, 422],
        'Debe confirmarse una sola solicitud. '.$resultados->toJson());
    $errores = json_decode($resultados->firstWhere('estado', 422)['contenido'], true, flags: JSON_THROW_ON_ERROR)['errors'];
    comprobar(isset($errores['inspecciones']), 'El conflicto debe informarse como una inspección ocupada.');
    comprobar($inspeccion->consolidados()->count() === 1, 'El vínculo debe ser único.');
}

DB::table('roles')->insertOrIgnore([['id' => 2, 'nombre' => 'Operador'], ['id' => 3, 'nombre' => 'Supervisor']]);
$principal = app(BodegaAccessService::class)->principal() ?? Bodega::create(['nombre' => 'Central QA', 'tipo' => 'Principal']);
$operadores = User::factory()->count(2)->create(['role_id' => 2, 'bodega_id' => $principal->id]);
$supervisor = User::factory()->create(['role_id' => 3]);
$coord = Role::where('ambito_operativo', 5)->sole();
$identificador = Str::random(8);
$fecha = today()->toDateString();
$regiones = collect(['Norte', 'Sur'])->map(function ($nombre) use ($coord, $identificador) {
    $usuario = User::factory()->create(['role_id' => $coord->id]);
    $region = Region::create(['nombre' => "$nombre QA $identificador", 'activa' => true]);
    $region->coordinador()->associate($usuario)->save();

    return $region;
});

$crearInspeccion = function (Region $region) use ($supervisor, $fecha) {
    $codigo = 'QA-'.Str::random(12);
    Colaborador::create(['codigo' => $codigo, 'nombre' => 'Persona QA', 'puesto' => 'Técnico', 'region_id' => $region->id, 'estado' => 'Activo']);

    return Inspeccion::create(['supervisor_id' => $supervisor->id, 'region_id' => $region->id, 'colaborador_codigo' => $codigo,
        'puesto' => 'Técnico', 'fecha' => $fecha, 'solicitud_id' => Str::uuid()->toString()]);
};
$crearCorte = function (User $usuario, array $seleccion) use ($fecha, $identificador) {
    $corte = CorteRequerimiento::create(['operador_id' => $usuario->id, 'nombre' => 'Corte QA '.$identificador, 'desde' => $fecha, 'hasta' => $fecha]);
    $servicio = app(CortesRequerimientos::class);
    comprobar($servicio->disponibles($usuario, $corte, anteriores: false)->whereKey($seleccion)->count() === count($seleccion),
        'El período de un día debe incluir sus inspecciones en MySQL.');
    $corte->inspecciones()->attach($seleccion, ['anterior' => false]);

    return ['usuario' => $usuario->id, 'ruta' => "/flujo/cortes/{$corte->id}/confirmar",
        'datos' => ['firma' => $servicio->vistaPrevia($corte)['firma']], 'corte' => $corte->id];
};

$compartida = $crearInspeccion($regiones[0]);
$extras = [$crearInspeccion($regiones[1]), $crearInspeccion($regiones[1])];
$peticiones = $operadores->map(fn (User $usuario, int $indice) => $crearCorte($usuario, [$compartida->id, $extras[$indice]->id]))->all();
confirmarEnParalelo($compartida, $peticiones);
$resultados = CorteRequerimiento::whereKey(array_column($peticiones, 'corte'))->withCount('consolidados')->get();
comprobar($resultados->pluck('consolidados_count')->sort()->values()->all() === [0, 2],
    'El corte ganador debe generar los dos consolidados y el otro debe conservarse en borrador.');
comprobar(collect($extras)->filter(fn ($fila) => $fila->consolidados()->exists())->count() === 1,
    'La inspección exclusiva del corte rechazado debe quedar disponible.');

$paraLegado = $crearInspeccion($regiones[0]);
confirmarEnParalelo($paraLegado, [
    $crearCorte($operadores[0], [$paraLegado->id]),
    ['usuario' => $operadores[1]->id, 'ruta' => '/flujo/consolidados', 'datos' => [
        'nombre' => 'Consolidados directos QA', 'desde' => $fecha, 'hasta' => $fecha, 'inspecciones' => [$paraLegado->id],
    ]],
]);

echo "MySQL verificó cortes simultáneos y compatibilidad concurrente con el consolidado directo.\n";
