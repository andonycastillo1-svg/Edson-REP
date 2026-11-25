@extends('layouts.app')

@section('title', 'Panel de Recursos Humanos')

@section('content')
    <x-panel-operativo titulo="Panel de Recursos Humanos" :notas="['rrhh.alertas.index' => trans_choice('{0} Sin incidencias abiertas|{1} :count incidencia abierta|[2,*] :count incidencias abiertas', $totalAlertas ?? 0)]" />
@endsection
