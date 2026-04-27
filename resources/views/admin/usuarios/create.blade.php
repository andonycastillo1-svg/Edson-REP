@extends('layouts.admin')

@section('title', 'Crear usuario')

@section('content')
@php
    $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
@endphp
<div class="ui-panel w-full max-w-3xl p-6 md:p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Crear usuario</h1>
            <p class="text-sm text-gray-500">Registra un nuevo usuario para el sistema</p>
        </div>

        <a href="{{ route($routePrefix . '.usuarios.index') }}"
           class="ui-btn-secondary">
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
                    <option value="">Seleccione...</option>

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
                    <option value="">Seleccione...</option>
                    @foreach($bodegas as $b)
                        <option value="{{ $b->id }}" {{ old('bodega_id') == $b->id ? 'selected' : '' }}>
                            {{ $b->nombre }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Asigna la bodega que administrará este usuario.</p>
            </div>

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
               class="ui-btn-secondary">
                Cancelar
            </a>

            <button type="submit"
                    class="ui-btn-success">
                Guardar
            </button>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rolSelect = document.getElementById('role_id');
    const wrap = document.getElementById('bodega_wrap');
    const bodegaSelect = document.getElementById('bodega_id');

    // ID del rol habilitado (Operador / fallback Encargado) viene desde el controller
    const ROL_ENCARGADO_ID = {{ $rolEncargadoId ?? 2 }};

    function toggleBodega() {
        const isEncargado = parseInt(rolSelect.value || '0') === parseInt(ROL_ENCARGADO_ID);
        wrap.classList.toggle('hidden', !isEncargado);

        // si no es encargado, limpiamos bodega
        if (!isEncargado && bodegaSelect) {
            bodegaSelect.value = '';
        }
    }

    if (rolSelect) {
        rolSelect.addEventListener('change', toggleBodega);
        toggleBodega();
    }
});
</script>
@endsection
