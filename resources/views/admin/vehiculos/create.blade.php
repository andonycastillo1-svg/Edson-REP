@extends('layouts.admin')

@section('title', 'Nuevo Vehículo')

@section('content')
<div class="ui-panel w-full max-w-3xl p-6 md:p-8">
    <x-internal-navigation :back-url="route('admin.vehiculos.index')" />

    <div class="flex items-center justify-between mb-6">
        <h1 class="ui-page-title text-slate-800">Nuevo Vehículo</h1>
    </div>

    <form enctype="multipart/form-data" action="{{ route('admin.vehiculos.store') }}" method="POST" class="ui-form space-y-6">
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

        <x-sistema.campos-nativos modulo="vehiculos" />



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

        <x-sistema.campos modulo="vehiculos" />

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
