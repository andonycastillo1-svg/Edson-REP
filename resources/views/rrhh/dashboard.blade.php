@extends('layouts.admin')

@section('title', 'Panel de Recursos Humanos')

@section('content')
    <x-panel-operativo titulo="Panel de Recursos Humanos" :accesos="[
        'rrhh.colaboradores.index' => ['Colaboradores', 'Altas, bajas y gestión de personal', '👥'],
        'rrhh.usuarios.index' => ['Usuarios', 'Gestión de cuentas', '👤'],
        'rrhh.alertas.index' => ['Alertas', trans_choice('{0} Sin alertas pendientes|{1} :count alerta pendiente|[2,*] :count alertas pendientes', $totalAlertas ?? 0), '⚠'],
    ]" />
@endsection
