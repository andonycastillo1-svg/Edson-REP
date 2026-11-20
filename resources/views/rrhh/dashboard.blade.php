@extends('layouts.admin')

@section('title', 'Panel de Recursos Humanos')

@section('content')
    <x-panel-operativo titulo="Panel de Recursos Humanos" :accesos="[
        'rrhh.colaboradores.index' => ['Colaboradores', 'Altas, bajas y gestión de personal', '👥'],
        'rrhh.usuarios.index' => ['Usuarios', 'Gestión de cuentas', '👤'],
        'rrhh.alertas.index' => ['Incidencias para revisión de RR. HH.', trans_choice('{0} Sin incidencias abiertas|{1} :count incidencia abierta|[2,*] :count incidencias abiertas', $totalAlertas ?? 0), '⚠'],
    ]" />
@endsection
