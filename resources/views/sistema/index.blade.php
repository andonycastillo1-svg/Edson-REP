<x-sistema.pagina titulo="Configuración del sistema">
    @php($acceso = app(\App\Sistema\Permisos::class))
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if($acceso->esSuperadministrador(auth()->user()))
            <a class="dashboard-card px-6 py-5" href="{{ route('sistema.seguridad') }}">Roles y permisos</a>
        @endif
        @foreach([
            'campos' => 'Campos personalizados',
            'presentacion' => 'Personalización de formularios y tablas',
            'parametros' => 'Parámetros generales',
            'notificaciones' => 'Configuración de notificaciones',
            'auditoria' => 'Bitácora de auditoría',
        ] as $seccion => $titulo)
            @if($acceso->permite(auth()->user(), 'sistema.'.$seccion))
                <a class="dashboard-card px-6 py-5" href="{{ route('sistema.'.$seccion) }}">{{ $titulo }}</a>
            @endif
        @endforeach
    </div>
</x-sistema.pagina>
