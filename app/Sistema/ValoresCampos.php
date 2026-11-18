<?php

namespace App\Sistema;

use App\Models\User;
use App\Services\DocumentoPrivado;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ValoresCampos
{
    public const TIPOS = [
        'texto' => 'Texto corto', 'texto_largo' => 'Texto largo', 'entero' => 'Número entero',
        'decimal' => 'Número decimal', 'fecha' => 'Fecha', 'fecha_hora' => 'Fecha y hora',
        'correo' => 'Correo electrónico', 'telefono' => 'Teléfono', 'lista' => 'Lista desplegable',
        'multiple' => 'Selección múltiple', 'casilla' => 'Casilla', 'booleano' => 'Sí / No', 'archivo' => 'Archivo',
    ];

    public function __construct(private Permisos $permisos, private Auditoria $auditoria) {}

    public function campos(string $modulo, User $user, ?string $superficie = null, bool $historicos = false): Collection
    {
        if (!$this->permisos->instalado()) {
            return collect();
        }

        return Campo::where('modulo', $modulo)
            ->when(!$historicos, fn ($q) => $q->where('activo', true)->whereNull('archivado_en'))
            ->when($superficie, fn ($q) => $q->where($superficie, true))
            ->with('opciones')->orderBy('orden')->orderBy('id')->get()
            ->filter(fn (Campo $campo) => !$campo->permiso_lectura || $this->permisos->permite($user, $campo->permiso_lectura));
    }

    public function editable(Campo $campo, User $user): bool
    {
        return !$campo->permiso_edicion || $this->permisos->permite($user, $campo->permiso_edicion);
    }

    public function cargar(Collection $campos, iterable $registros): Collection
    {
        if ($campos->isEmpty()) {
            return collect();
        }

        return DB::table('sistema_campo_valores')->whereIn('campo_id', $campos->pluck('id'))->whereIn('registro', collect($registros)->map(fn ($id) => (string) $id))
            ->get()->groupBy('registro')->map(fn (Collection $items) => $items->mapWithKeys(
                fn ($item) => [$item->campo_id => json_decode($item->valor, true, flags: JSON_THROW_ON_ERROR)]
            ));
    }

    public function preparar(Request $request, string $modulo, bool $nuevo, ?string $registro = null, ?array $entrada = null): array
    {
        $campos = $this->campos($modulo, $request->user(), 'formulario')
            ->filter(fn (Campo $campo) => $this->editable($campo, $request->user()))->keyBy('clave');
        $entrada ??= $request->all('campos_personalizados')['campos_personalizados'] ?? [];
        if (!is_array($entrada) || array_diff(array_keys($entrada), $campos->keys()->all())) {
            throw ValidationException::withMessages(['campos_personalizados' => 'Hay un campo desconocido o sin permiso de edición.']);
        }

        $actuales = $registro === null ? collect() : ($this->cargar($campos, [$registro])->get($registro) ?? collect());
        $lista = [];
        foreach ($campos as $campo) {
            $presente = array_key_exists($campo->clave, $entrada);
            if (!$presente && !$nuevo) {
                continue;
            }

            $valor = $presente ? $entrada[$campo->clave] : $campo->predeterminado;
            $lista[$campo->id] = $this->validar($campo, $valor, $nuevo && $campo->obligatorio, $actuales->get($campo->id));
        }

        return $lista;
    }

    public function validar(Campo $campo, mixed $valor, bool $obligatorio = false, mixed $actual = null): mixed
    {
        if ($campo->tipo === 'multiple' && is_array($valor)) {
            $valor = array_values(array_filter($valor, fn ($item) => $item !== null && $item !== ''));
        }

        $reglas = match ($campo->tipo) {
            'texto' => ['string', 'max:190'],
            'texto_largo' => ['string', 'max:5000'],
            'entero' => ['integer', 'between:-2147483648,2147483647'],
            'decimal' => ['numeric', 'decimal:0,4', 'between:-9999999999,9999999999'],
            'fecha' => ['date_format:Y-m-d'],
            'fecha_hora' => ['date_format:Y-m-d\TH:i'],
            'correo' => ['email:rfc', 'max:190'],
            'telefono' => ['string', 'regex:/^\+?[0-9 ().-]{4,35}$/'],
            'lista' => [Rule::in($this->opcionesPermitidas($campo, [$actual]))],
            'multiple' => ['array', 'list', 'max:30'],
            'casilla', 'booleano' => ['boolean'],
            'archivo' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx,txt', 'max:10240'],
        };
        $key = 'campos_personalizados.'.$campo->clave;
        $datos = Validator::make(['campos_personalizados' => [$campo->clave => $valor]], [
            $key => [$obligatorio ? 'required' : 'nullable', ...$reglas],
            ...($campo->tipo === 'multiple' ? [$key.'.*' => ['required', 'distinct', Rule::in($this->opcionesPermitidas($campo, (array) $actual))]] : []),
        ], [], [$key => $campo->etiqueta])->validate();
        $validado = data_get($datos, $key);
        if ($validado === null) {
            return null;
        }

        return match ($campo->tipo) {
            'entero', 'lista' => (int) $validado,
            'multiple' => array_map('intval', $validado),
            'decimal' => (string) $validado,
            'casilla', 'booleano' => (bool) $validado,
            default => $validado,
        };
    }

    private function opcionesPermitidas(Campo $campo, array $actual): array
    {
        return $campo->opciones->filter(fn (OpcionCampo $opcion) => $opcion->activo || in_array($opcion->id, $actual, true))->pluck('id')->all();
    }

    public function guardar(string $modulo, string|int $registro, array $valores): void
    {
        $registro = (string) $registro;
        foreach ($valores as $campo => $valor) {
            $anterior = DB::table('sistema_campo_valores')->where(['campo_id' => $campo, 'registro' => $registro])->lockForUpdate()->first();
            if ($valor instanceof UploadedFile) {
                $ruta = DocumentoPrivado::guardar($valor, 'campos-sistema');
                if (app()->bound('request')) {
                    $temporal = request()->attributes->get('archivos_campos_sistema', []);
                    request()->attributes->set('archivos_campos_sistema', [...$temporal, $ruta]);
                }
                $valor = ['ruta' => $ruta, 'nombre' => $valor->getClientOriginalName()];
            }
            $json = json_encode($valor, JSON_THROW_ON_ERROR);
            if (($anterior === null && $valor === null) || ($anterior && json_decode($anterior->valor, true) === $valor)) {
                continue;
            }

            if ($anterior) {
                DB::table('sistema_campo_historial')->insert([
                    'campo_id' => $campo, 'registro' => $registro, 'valor' => $anterior->valor,
                    'actor_id' => auth()->id(), 'created_at' => now(),
                ]);
            }
            $busqueda = is_scalar($valor) ? mb_substr((string) (is_bool($valor) ? (int) $valor : $valor), 0, 190) : null;
            DB::table('sistema_campo_valores')->upsert([
                'campo_id' => $campo, 'registro' => $registro, 'valor' => $json,
                'busqueda' => $busqueda, 'created_at' => now(), 'updated_at' => now(),
            ], ['campo_id', 'registro'], ['valor', 'busqueda', 'updated_at']);
            $this->auditoria->registrar($modulo, 'guardar_campo', $registro, null, ['campo_id' => $campo]);
        }
    }

    public function predeterminadosAutomaticos(string $modulo): array
    {
        if (!$this->permisos->instalado()) {
            return [];
        }

        return Campo::where('modulo', $modulo)->where('activo', true)->whereNull('archivado_en')
            ->whereNotNull('predeterminado')->get()
            ->filter(fn (Campo $campo) => $campo->predeterminado !== null)
            ->mapWithKeys(fn (Campo $campo) => [$campo->id => $campo->predeterminado])->all();
    }

    public function verificarClaveDisponible(string $modulo, string $registro): void
    {
        if ($this->permisos->instalado() && DB::table('sistema_campo_valores')->where('registro', $registro)
            ->whereIn('campo_id', Campo::where('modulo', $modulo)->select('id'))->exists()) {
            throw ValidationException::withMessages(['registro' => 'El identificador tiene un historial y no se puede reutilizar.']);
        }
    }

    public function trasladarClave(string $modulo, string $anterior, string $nueva): void
    {
        if ($anterior === $nueva || !$this->permisos->instalado()) {
            return;
        }
        foreach (['sistema_campo_valores', 'sistema_campo_historial'] as $tabla) {
            DB::table($tabla)->where('registro', $anterior)->whereIn('campo_id', Campo::where('modulo', $modulo)->select('id'))
                ->update(['registro' => $nueva]);
        }
    }

    public function mostrar(Campo $campo, mixed $valor, bool $marcarInactivas = false): string
    {
        return match ($campo->tipo) {
            'casilla', 'booleano' => $valor === null ? '' : ($valor ? 'Sí' : 'No'),
            'lista', 'multiple' => $campo->opciones->whereIn('id', (array) $valor)
                ->map(fn ($opcion) => $opcion->etiqueta.($marcarInactivas && !$opcion->activo ? ' (inactiva)' : ''))->implode(', '),
            'archivo' => $valor['nombre'] ?? '',
            default => (string) $valor,
        };
    }
}
