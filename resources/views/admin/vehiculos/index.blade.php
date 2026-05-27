@extends('layouts.admin')

@section('title','Vehículos')

@section('content')

<div class="ui-panel w-full max-w-6xl p-6 md:p-8">

    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="section-kicker">Flota</div>
            <h1 class="text-2xl font-bold text-slate-900">Vehículos</h1>
            <p class="text-sm text-slate-600">Control visual de vehículos disponibles, en uso o mantenimiento.</p>
        </div>

        <div class='flex gap-2'><a href="{{ route('admin.vehiculos.asignaciones.index') }}" class="ui-btn-primary">Asignaciones</a><a href="{{ route('admin.vehiculos.create') }}"
           class="ui-btn-success">
           + Nuevo vehículo
        </a></div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    <th class="p-3 text-left">VIN</th>
                    <th class="p-3 text-left">Placa</th>
                    <th class="p-3 text-left">Marca</th>
                    <th class="p-3 text-left">Modelo</th>
                    <th class="p-3 text-left">Estado</th>
                    <th class="p-3 text-center">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">

                @foreach($vehiculos as $vehiculo)

                <tr class="hover:bg-sky-50/60">

                    <td class="p-3">{{ $vehiculo->vin }}</td>

                    <td class="p-3">{{ $vehiculo->placa }}</td>

                    <td class="p-3">{{ $vehiculo->marca }}</td>

                    <td class="p-3">{{ $vehiculo->modelo }}</td>

                    <td class="p-3">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $vehiculo->estado }}</span>
                    </td>

                    <td class="p-3">
                        <div class="flex justify-center gap-2">

                        <a href="{{ route('admin.vehiculos.edit',$vehiculo->vin) }}"
                           class="ui-btn-edit px-3 py-1.5 text-xs">
                           Editar
                        </a>

                        <form action="{{ route('admin.vehiculos.destroy',$vehiculo->vin) }}"
                              method="POST">
                            @csrf
                            @method('DELETE')

                            <button class="ui-btn-danger px-3 py-1.5 text-xs">
                                Eliminar
                            </button>
                        </form>
                        </div>

                    </td>

                </tr>

                @endforeach

            </tbody>

        </table>

    </div>

    <div class="mt-6">
        {{ $vehiculos->links() }}
    </div>

</div>

@endsection
