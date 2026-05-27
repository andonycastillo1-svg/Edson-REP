@extends('layouts.admin')
@section('title','Asignaciones de vehículos')
@section('content')
<div class="ui-panel p-6">
  <div class="flex justify-between mb-4"><h1 class="text-xl font-semibold">Asignaciones de vehículos</h1><a class="ui-btn-primary" href="{{ route('admin.vehiculos.asignaciones.create') }}">+ Nueva asignación</a></div>
  @if(session('success'))<div class="mb-3 text-green-700">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="mb-3 text-red-700">{{ session('error') }}</div>@endif
  <table class="w-full text-sm"><thead><tr><th>Vehículo</th><th>Colaborador</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
  @foreach($asignaciones as $a)
  <tr>
    <td>{{ $a->vehiculo_vin }}</td><td>{{ optional($a->colaborador)->nombre }}</td><td>{{ optional($a->fecha_inicio)?->format('d/m/Y') }}</td><td>{{ optional($a->fecha_fin)?->format('d/m/Y') ?? '-' }}</td><td>{{ $a->activa ? 'Activa' : 'Cerrada' }}</td>
    <td>
      <a target="_blank" href="{{ route('admin.vehiculos.asignaciones.pdf_asignacion', $a) }}">PDF Asig.</a>
      @if(!$a->activa)<a target="_blank" href="{{ route('admin.vehiculos.asignaciones.pdf_desasignacion', $a) }}">PDF Desasig.</a>@endif
      @if($a->activa)
      <form method="POST" action="{{ route('admin.vehiculos.asignaciones.desasignar', $a) }}">@csrf
        <input type="date" name="fecha_fin" required>
        <input type="text" name="estado_final_vehiculo" placeholder="Estado final" required>
        <button>Desasignar</button>
      </form>
      @endif
    </td>
  </tr>
  @endforeach
  </tbody></table>
  <div class="mt-4">{{ $asignaciones->links() }}</div>
</div>
@endsection
