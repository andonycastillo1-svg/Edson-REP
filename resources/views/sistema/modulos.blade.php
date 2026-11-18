<x-sistema.pagina titulo="Datos de los módulos">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($modulos as $modulo => $spec)
            <a class="dashboard-card px-6 py-5" href="{{ route('sistema.datos.lista', $modulo) }}">{{ $spec[0] }}</a>
        @empty
            <x-sistema.panel>No tienes módulos disponibles para consulta.</x-sistema.panel>
        @endforelse
    </div>
</x-sistema.pagina>
