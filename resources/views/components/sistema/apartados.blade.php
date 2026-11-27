@props(['inicio' => false])
@php
    $usuario = auth()->user();
    $acceso = app(\App\Sistema\Permisos::class);
    $apartados = [
        'seguridad' => ['Roles y permisos', 'Administra el acceso por rol y las excepciones por cuenta.', ['sistema.seguridad', 'sistema.rol*', 'sistema.usuario*']],
        'campos' => ['Campos personalizados', 'Agrega datos a los módulos y decide dónde se muestran.', ['sistema.campos*']],
        'presentacion' => ['Formularios y tablas', 'Organiza los campos y columnas para todas las cuentas.', ['sistema.presentacion*']],
        'parametros' => ['Parámetros generales', 'Configura identidad, inventario y opciones de sesión.', ['sistema.parametros*']],
        'notificaciones' => ['Notificaciones', 'Elige qué avisos se envían y a quién.', ['sistema.notificaciones*']],
        'documentos' => ['Plantillas de documentos', 'Configura y publica formatos de asignación, traslado y devolución.', ['sistema.documentos*']],
        'auditoria' => ['Bitácora', 'Consulta quién realizó un cambio y cuándo.', ['sistema.auditoria']],
    ];
@endphp
<nav aria-label="Apartados de configuración" @class(['grid gap-3 sm:grid-cols-2 lg:grid-cols-3' => $inicio, 'sistema-pestanas' => !$inicio])>
    @foreach($apartados as $seccion => [$titulo, $descripcion, $rutas])
        @if(in_array($seccion, ['seguridad', 'documentos']) ? $acceso->esSuperadministrador($usuario) : $acceso->permite($usuario, 'sistema.'.$seccion))
            <a href="{{ route('sistema.'.$seccion) }}" @if(request()->routeIs(...$rutas)) aria-current="page" @endif
               @class(['dashboard-card flex min-h-20 items-center justify-between gap-3' => $inicio])>
                <span>
                    <span class="block font-bold">{{ $titulo }}</span>
                    @if($inicio)<span class="mt-1 block text-sm text-slate-600">{{ $descripcion }}</span>@endif
                </span>
                @if($inicio)<span aria-hidden="true" class="text-xl text-blue-700">›</span>@endif
            </a>
        @endif
    @endforeach
</nav>
