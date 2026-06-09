@extends('layouts.admin')

@section('title', 'Crear usuario')

@section('content')
@php
    $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
@endphp
<div class="w-full max-w-3xl bg-white/95 backdrop-blur rounded-2xl shadow-2xl p-6 md:p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Crear usuario</h1>
            <p class="text-sm text-gray-500">Registra un nuevo usuario para el sistema</p>
        </div>

        <a href="{{ route($routePrefix . '.usuarios.index') }}"
           class="text-sm text-blue-600 hover:underline">
            ← Volver
        </a>
    </div>

    <!-- Errores -->
    @if ($errors->any())
        <div class="mb-6 rounded-xl bg-red-50 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route($routePrefix . '.usuarios.store') }}" class="space-y-5">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Nombre -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <!-- Correo -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <!-- Rol -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Rol</label>
                <select name="role_id" id="role_id" required
                        class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    @if(auth()->user()->role_id != 4)<option value="">Seleccione...</option>@endif

                    @foreach($roles as $rol)
                        <option value="{{ $rol->id }}" {{ (int) old('role_id') === (int) $rol->id ? 'selected' : '' }}>
                            {{ $rol->nombre }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Define el acceso del usuario.</p>
            </div>

            <!-- Bodega (según rol habilitado) -->
            <div id="bodega_wrap" class="hidden">
                <label class="block text-sm font-medium text-gray-700">Bodega</label>
                <select name="bodega_id" id="bodega_id"
                        class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    @if(auth()->user()->role_id != 4)<option value="">Seleccione...</option>@endif
                    @foreach($bodegas as $b)
                        <option value="{{ $b->id }}" {{ old('bodega_id') == $b->id ? 'selected' : '' }}>
                            {{ $b->nombre }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Asigna la bodega que administrará este usuario.</p>
            </div>

            @if(auth()->user()->role_id == 1)
                <!-- Relación administrada únicamente por Admin -->
                <div id="supervisores_wrap" class="hidden md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Supervisores relacionados</label>
                    <select name="supervisores[]" id="supervisores" multiple
                            class="mt-1 w-full min-h-32 rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        @foreach($supervisores as $supervisor)
                            <option value="{{ $supervisor->id }}"
                                @selected(in_array((int) $supervisor->id, array_map('intval', old('supervisores', []))))>
                                {{ $supervisor->name }} ({{ $supervisor->email }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Usa Ctrl/Cmd para seleccionar varios. Solo se guarda cuando el usuario es almacenista/encargado.</p>
                </div>
            @endif

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                <input type="password" name="password" required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <!-- Confirmación -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>

        <!-- Botones -->
        <div class="pt-4 border-t flex flex-col sm:flex-row gap-3 justify-end">
            <a href="{{ route($routePrefix . '.usuarios.index') }}"
               class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-gray-700 font-semibold hover:bg-gray-50 transition">
                Cancelar
            </a>

            <button type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-white font-semibold shadow hover:bg-blue-700 transition">
                Guardar
            </button>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rolSelect = document.getElementById('role_id');
    const bodegaWrap = document.getElementById('bodega_wrap');
    const bodegaSelect = document.getElementById('bodega_id');
    const supervisoresWrap = document.getElementById('supervisores_wrap');
    const supervisoresSelect = document.getElementById('supervisores');

    function toggleCamposPorRol() {
        const roleId = parseInt(rolSelect.value || '0');
        const esAlmacenista = roleId === parseInt(ROL_ALMACENISTA_ID);
        const esSupervisor = roleId === parseInt(ROL_SUPERVISOR_ID);

        bodegaWrap.classList.toggle('hidden', !esAlmacenista);
        bodegaSelect.disabled = !esAlmacenista;
        bodegaSelect.required = esAlmacenista;

        if (supervisoresWrap) {
            supervisoresWrap.classList.toggle('hidden', !isEncargado);
        }

        // si no es encargado, limpiamos bodega
        if (!isEncargado && bodegaSelect) {
            bodegaSelect.value = '';
        }

        if (!isEncargado && supervisoresSelect) {
            Array.from(supervisoresSelect.options).forEach(option => option.selected = false);
        }
    }

    if (rolSelect) {
        rolSelect.addEventListener('change', toggleCamposPorRol);
        toggleCamposPorRol();
    }
});
</script>
@endsection
