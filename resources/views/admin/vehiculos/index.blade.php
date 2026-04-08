@extends('layouts.admin')

@section('title','Vehículos')

@section('content')

<div class="w-full max-w-6xl bg-white/90 rounded-2xl shadow-2xl p-8">

    <div class="flex justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">
            Vehículos
        </h1>

        <a href="{{ route('admin.vehiculos.create') }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
           Nuevo vehículo
        </a>
    </div>

    <div class="overflow-x-auto">

        <table class="w-full border border-slate-200">

            <thead class="bg-slate-100">
                <tr>
                    <th class="p-3 text-left">VIN</th>
                    <th class="p-3 text-left">Placa</th>
                    <th class="p-3 text-left">Marca</th>
                    <th class="p-3 text-left">Modelo</th>
                    <th class="p-3 text-left">Estado</th>
                    <th class="p-3 text-center">Acciones</th>
                </tr>
            </thead>

            <tbody>

                @foreach($vehiculos as $vehiculo)

                <tr class="border-t hover:bg-slate-50">

                    <td class="p-3">{{ $vehiculo->vin }}</td>

                    <td class="p-3">{{ $vehiculo->placa }}</td>

                    <td class="p-3">{{ $vehiculo->marca }}</td>

                    <td class="p-3">{{ $vehiculo->modelo }}</td>

                    <td class="p-3">{{ $vehiculo->estado }}</td>

                    <td class="p-3 text-center flex gap-2 justify-center">

                        <a href="{{ route('admin.vehiculos.edit',$vehiculo->vin) }}"
                           class="px-3 py-1 bg-blue-500 text-white rounded">
                           Editar
                        </a>

                        <form action="{{ route('admin.vehiculos.destroy',$vehiculo->vin) }}"
                              method="POST">
                            @csrf
                            @method('DELETE')

                            <button class="px-3 py-1 bg-red-500 text-white rounded">
                                Eliminar
                            </button>
                        </form>

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
