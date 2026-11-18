@extends('layouts.operador')

@section('title', 'Panel de supervisión')

@section('content')
    <x-panel-operativo titulo="Panel de supervisión" :accesos="[
        'supervisor.asignaciones.index' => ['Asignaciones relacionadas', 'Entregas y evidencias de los almacenistas asignados', '📦'],
    ]" />
@endsection
