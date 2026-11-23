<?php

use App\Models\Bodega;
use App\Models\Consolidado;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
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

if (($argv[1] ?? '') === '--comprar') {
    Auth::guard('web')->setUser(User::findOrFail($argv[2]));
    $peticion = Request::create('/operador/compras', 'POST', json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR),
        server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_REFERER' => '/operador/compras/create']);
    echo "LISTA\n";
    flush();
    $respuesta = $app->make(HttpKernel::class)->handle($peticion);
    echo json_encode([
        'estado' => $respuesta->getStatusCode(),
        'errores' => array_keys(json_decode($respuesta->getContent(), true)['errors'] ?? []),
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
    ?? Bodega::create(['nombre' => 'Central compras QA', 'tipo' => 'Principal']);
$coordinador = User::factory()->create(['role_id' => Role::where('ambito_operativo', 5)->firstOrFail()->id]);
$usuarios = collect([1, 2])->map(fn () => User::factory()->create(['role_id' => 2, 'bodega_id' => $bodega->id]));
$prefijo = 'QA-'.strtoupper(Str::random(8));
$proveedor = DB::table('proveedores')->insertGetId(['nombre' => $prefijo]);

$preparar = function (string $codigo) use ($usuarios, $coordinador, $proveedor) {
    Producto::create(['codigo' => $codigo, 'nombre' => 'Equipo QA', 'vida_util_meses' => 6]);
    $consolidado = Consolidado::create([
        'nombre' => $codigo, 'coordinador_id' => $coordinador->id, 'operador_id' => $usuarios->first()->id,
        'desde' => today(), 'hasta' => today(), 'estado' => 'aprobado', 'aprobado_por' => $coordinador->id, 'aprobado_en' => now(),
    ]);
    $consolidado->lineas()->create(['producto_codigo' => $codigo, 'original' => 5, 'cantidad' => 5]);

    return [$consolidado, [
        'fecha_compra' => today()->toDateString(), 'no_factura' => $codigo, 'proveedor_tipo' => 'existente',
        'proveedor_id' => $proveedor, 'autorizado_por' => 'Gerencia QA', 'forma_pago' => 'Al contado', 'producto_tipo' => ['existente'],
        'producto_codigo' => [$codigo], 'cantidad' => [4], 'precio_unitario' => ['9.00'],
        'consolidados' => [$consolidado->id], 'distribucion' => [$consolidado->id => [bin2hex($codigo) => 4]],
    ]];
};

// Registra historial para que el bloqueo inicial de configuración no serialice las compras bajo prueba.
[$consolidado, $datos] = $preparar($prefijo.'-BASE');
Auth::guard('web')->setUser($usuarios->first());
$respuesta = $app->make(HttpKernel::class)->handle(Request::create('/operador/compras', 'POST', $datos,
    server: ['HTTP_ACCEPT' => 'application/json']));
comprobar($respuesta->getStatusCode() === 302, 'Debe poder registrarse la compra inicial.');

foreach (['MISMA', 'DISTINTA'] as $caso) {
    [$consolidado, $datos] = $preparar($prefijo.'-'.$caso);
    DB::beginTransaction();
    try {
        Consolidado::whereKey($consolidado->id)->lockForUpdate()->firstOrFail();
        $procesos = $usuarios->map(function (User $usuario, int $indice) use ($caso, $datos) {
            $carga = $caso === 'DISTINTA' ? [...$datos, 'no_factura' => $datos['no_factura'].'-'.$indice] : $datos;
            $proceso = new Process([PHP_BINARY, __FILE__, '--comprar', (string) $usuario->id, json_encode($carga, JSON_THROW_ON_ERROR)]);
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
    $esperado = $caso === 'DISTINTA' ? [302, 422] : [302, 302];
    comprobar($resultados->pluck('estado')->sort()->values()->all() === $esperado, $caso.': '.$resultados->toJson());
    if ($caso === 'DISTINTA') {
        comprobar($resultados->firstWhere('estado', 422)['errores'] === ['distribucion'], 'La segunda factura excede el pendiente.');
    }
    $codigo = $datos['producto_codigo'][0];
    comprobar(DB::table('compra_consolidado')->where('consolidado_id', $consolidado->id)->count() === 1, 'Debe quedar una sola factura vinculada.');
    comprobar((int) DB::table('compra_detalle_consolidado')->where('consolidado_linea_id', $consolidado->lineas()->sole()->id)->sum('cantidad') === 4,
        'Deben distribuirse cuatro unidades una sola vez.');
    comprobar(DB::table('movimientos')->where('producto_codigo', $codigo)->count() === 1, 'Debe existir un solo movimiento de inventario.');
    comprobar((int) DB::table('inventarios')->where('producto_codigo', $codigo)->sum('cantidad') === 4, 'El saldo debe reflejar una única entrada.');
}

echo "MySQL: las compras concurrentes respetan el pendiente del consolidado y los reintentos no repiten la factura ni el movimiento.\n";
