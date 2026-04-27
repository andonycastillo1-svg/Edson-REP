@extends('layouts.admin')

@section('title', 'Editar usuario')

@section('content')
@php
    $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
@endphp
<div class="ui-panel w-full max-w-3xl p-6 md:p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Editar usuario</h1>
            <p class="text-sm text-gray-500">Modifica la información del usuario</p>
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

    <form method="POST" action="{{ route($routePrefix . '.usuarios.update', $usuario->id) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Nombre -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" name="name"
                       value="{{ old('name', $usuario->name) }}" required
                    class="mt-1 w-full rounded-xl border-gray-300 focus:border-sky-500 focus:ring-sky-500">
            </div>

            <!-- Correo -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Correo</label>
                <input type="email" name="email"
                       value="{{ old('email', $usuario->email) }}" required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-sky-500 focus:ring-sky-500">
            </div>

            <!-- Rol -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Rol</label>
                <select name="role_id" id="role_id" required
                        class="mt-1 w-full rounded-xl border-gray-300 focus:border-sky-500 focus:ring-sky-500">

                    @foreach($roles as $rol)
                        <option value="{{ $rol->id }}" {{ (int) old('role_id', $usuario->role_id) === (int) $rol->id ? 'selected' : '' }}>
                            {{ $rol->nombre }}
                        </option>
                    @endforeach

                </select>
            </div>

            <!-- Bodega (según rol habilitado) -->
            <div id="bodega_wrap" class="hidden">
                <label class="block text-sm font-medium text-gray-700">Bodega</label>
                <select name="bodega_id" id="bodega_id"
                        class="mt-1 w-full rounded-xl border-gray-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Seleccione...</option>
                    @foreach($bodegas as $b)
                        <option value="{{ $b->id }}"
                            {{ (int)old('bodega_id', $usuario->bodega_id) === (int)$b->id ? 'selected' : '' }}>
                            {{ $b->nombre }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Asigna la bodega que administrará este usuario.</p>
            </div>

            <!-- Nueva contraseña -->
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Nueva contraseña <span class="text-gray-400">(opcional)</span>
                </label>
                <input type="password" name="password"
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-sky-500 focus:ring-sky-500">
            </div>

            <!-- Confirmación -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">
                    Confirmar nueva contraseña
                </label>
                <input type="password" name="password_confirmation"
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-sky-500 focus:ring-sky-500">
            </div>

        </div>

        <!-- Botones -->
        <div class="pt-4 border-t flex flex-col sm:flex-row gap-3 justify-end">
            <a href="{{ route($routePrefix . '.usuarios.index') }}"
               class="ui-btn-secondary">
                Cancelar
            </a>

            <button type="submit"
                    class="ui-btn-save">
                Actualizar
            </button>
        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rolSelect = document.getElementById('role_id');
    const wrap = document.getElementById('bodega_wrap');
    const bodegaSelect = document.getElementById('bodega_id');

    const ROL_ENCARGADO_ID = {{ $rolEncargadoId ?? 2 }};

    function toggleBodega() {
        const isEncargado = parseInt(rolSelect.value || '0') === parseInt(ROL_ENCARGADO_ID);
        wrap.classList.toggle('hidden', !isEncargado);

        // si no es encargado, limpiamos bodega (para que no guarde basura)
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
