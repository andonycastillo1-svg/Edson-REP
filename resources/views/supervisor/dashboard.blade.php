@extends('layouts.operador')

@section('title', 'Panel de supervisión')

@section('content')
    <x-panel-operativo titulo="Panel de supervisión" :accesos="[
        'supervisor.asignaciones.index' => ['Asignaciones relacionadas', 'Custodia, hojas firmadas de los almacenistas asignados', '📦'],
    ]" />
    @rutaDisponible('supervisor.asignaciones.index')
        <nav class="mx-auto mt-4 flex max-w-6xl flex-wrap gap-3" aria-label="Seguimiento de entregas">
            <a class="ui-btn-secondary" href="{{ route('supervisor.asignaciones.index', ['seguimiento' => 'documentacion']) }}">Hojas firmadas faltantes</a>
        </nav>
    @endrutaDisponible
    @include('flujo.accesos')
@endsection
