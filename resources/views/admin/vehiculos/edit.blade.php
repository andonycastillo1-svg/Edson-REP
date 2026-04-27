@extends('layouts.admin')

@section('title', 'Editar Vehículo')

@section('content')
<div class="ui-panel w-full max-w-3xl p-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-600">Flota</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Editar vehículo</h1>
        </div>

        <a href="{{ route('admin.vehiculos.index') }}"
           class="ui-btn-secondary">
            Volver
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl bg-red-100 border border-red-300 text-red-700 p-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.vehiculos.update', $vehiculo->vin) }}" method="POST" class="ui-form space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label for="vin" class="block text-sm font-semibold text-slate-700 mb-2">VIN</label>
            <input type="text" id="vin" value="{{ $vehiculo->vin }}"
                   class="w-full rounded-xl border border-slate-300 bg-slate-100 px-4 py-3"
                   disabled>
        </div>

        <div>
            <label for="placa" class="block text-sm font-semibold text-slate-700 mb-2">Placa</label>
            <input type="text" name="placa" id="placa" value="{{ old('placa', $vehiculo->placa) }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   maxlength="20" required>
        </div>

        <div>
            <label for="marca" class="block text-sm font-semibold text-slate-700 mb-2">Marca</label>
            <input type="text" name="marca" id="marca" value="{{ old('marca', $vehiculo->marca) }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   maxlength="50">
        </div>

        <div>
            <label for="modelo" class="block text-sm font-semibold text-slate-700 mb-2">Modelo</label>
            <input type="text" name="modelo" id="modelo" value="{{ old('modelo', $vehiculo->modelo) }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   maxlength="50">
        </div>

        <div>
            <label for="estado" class="block text-sm font-semibold text-slate-700 mb-2">Estado</label>
            <select name="estado" id="estado"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required>
                @foreach(['Disponible', 'En uso', 'Mantenimiento'] as $estado)
                    <option value="{{ $estado }}" {{ old('estado', $vehiculo->estado) === $estado ? 'selected' : '' }}>
                        {{ $estado }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
            <a href="{{ route('admin.vehiculos.index') }}"
               class="ui-btn-secondary">
                Cancelar
            </a>

            <button type="submit"
                    class="ui-btn-success">
                Guardar cambios
            </button>
        </div>
    </form>
</div>
@endsection
