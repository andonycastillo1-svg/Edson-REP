@extends('layouts.admin')

@section('title', 'Nuevo Vehículo')

@section('content')
<div class="ui-panel w-full max-w-3xl p-6 md:p-8">
    <x-internal-navigation :back-url="route('admin.vehiculos.index')" />

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Nuevo Vehículo</h1>
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

    <form action="{{ route('admin.vehiculos.store') }}" method="POST" class="ui-form space-y-6">
        @csrf

        <div>
            <label for="vin" class="block text-sm font-semibold text-slate-700 mb-2">VIN</label>
            <input type="text" name="vin" id="vin" value="{{ old('vin') }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   maxlength="50" required>
        </div>

        <div>
            <label for="placa" class="block text-sm font-semibold text-slate-700 mb-2">Placa</label>
            <input type="text" name="placa" id="placa" value="{{ old('placa') }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   maxlength="20" required>
        </div>

        <div>
            <label for="marca" class="block text-sm font-semibold text-slate-700 mb-2">Marca</label>
            <input type="text" name="marca" id="marca" value="{{ old('marca') }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   maxlength="50">
        </div>

        <div>
            <label for="modelo" class="block text-sm font-semibold text-slate-700 mb-2">Modelo</label>
            <input type="text" name="modelo" id="modelo" value="{{ old('modelo') }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   maxlength="50">
        </div>

        <div>
            <label for="estado" class="block text-sm font-semibold text-slate-700 mb-2">Estado</label>
            <select name="estado" id="estado"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required>
                <option value="">Seleccione un estado</option>
                <option value="Disponible" {{ old('estado') == 'Disponible' ? 'selected' : '' }}>Disponible</option>
                <option value="En uso" {{ old('estado') == 'En uso' ? 'selected' : '' }}>En uso</option>
                <option value="Mantenimiento" {{ old('estado') == 'Mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
            </select>
        </div>

        <div class="pt-4 flex justify-end gap-3">
            <a href="{{ route('admin.vehiculos.index') }}" class="ui-btn-secondary">
                Cancelar
            </a>

            <button type="submit" class="ui-btn-success">
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection
