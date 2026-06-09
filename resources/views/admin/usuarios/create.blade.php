@extends('layouts.admin')

@section('title', 'Crear usuario')

@section('content')
@php
    $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';

    $rolSeleccionadoId = (int) old('role_id', 0);

    $mostrarBodega = $rolSeleccionadoId === (int) $rolAlmacenistaId;
    $mostrarAlmacenista = $rolSeleccionadoId === (int) $rolSupervisorId;
@endphp

<div class="w-full max-w-3xl bg-white/95 backdrop-blur rounded-2xl shadow-2xl p-6 md:p-8">

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

            <div>
                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text"
                       name="name"
                       value="{{ old('name') }}"
                       required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Correo</label>
                <input type="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Rol</label>

                <select name="role_id"
                        id="role_id"
                        required
                        class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">

                    @if(auth()->user()->role_id != 4)
                        <option value="">Seleccione...</option>
                    @endif

                    @foreach($roles as $rol)
                        @php
                            $tipoRol = 'otro';

                            if ((int) $rol->id === (int) $rolAlmacenistaId) {
                                $tipoRol = 'almacenista';
                            }

                            if ((int) $rol->id === (int) $rolSupervisorId) {
                                $tipoRol = 'supervisor';
                            }
                        @endphp

                        <option value="{{ $rol->id }}"
                                data-role-type="{{ $tipoRol }}"
                                {{ $rolSeleccionadoId === (int) $rol->id ? 'selected' : '' }}>
                            {{ $rol->nombre }}
                        </option>
                    @endforeach
                </select>

                <p class="text-xs text-gray-400 mt-1">
                    Define el acceso del usuario.
                </p>
            </div>

            <div id="bodega_wrap" class="{{ $mostrarBodega ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700">Bodega</label>

                <select name="bodega_id"
                        id="bodega_id"
                        {{ $mostrarBodega ? 'required' : 'disabled' }}
                        class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">

                    <option value="">Seleccione...</option>

                    @foreach($bodegas as $b)
                        <option value="{{ $b->id }}" {{ old('bodega_id') == $b->id ? 'selected' : '' }}>
                            {{ $b->nombre }}
                        </option>
                    @endforeach
                </select>

                <p class="text-xs text-gray-400 mt-1">
                    Asigna la bodega que administrará este usuario.
                </p>
            </div>

            <div id="almacenista_wrap" class="{{ $mostrarAlmacenista ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700">Almacenista asignado</label>

                <select name="almacenista_id"
                        id="almacenista_id"
                        {{ $mostrarAlmacenista ? 'required' : 'disabled' }}
                        class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">

                    <option value="">Seleccione...</option>

                    @foreach($almacenistas as $almacenista)
                        <option value="{{ $almacenista->id }}"
                                {{ old('almacenista_id') == $almacenista->id ? 'selected' : '' }}>
                            {{ $almacenista->name }} ({{ $almacenista->email }})
                        </option>
                    @endforeach
                </select>

                <p class="text-xs text-gray-400 mt-1">
                    Selecciona el almacenista al que pertenece este supervisor.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                <input type="password"
                       name="password"
                       required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                <input type="password"
                       name="password_confirmation"
                       required
                       class="mt-1 w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>

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

    const almacenistaWrap = document.getElementById('almacenista_wrap');
    const almacenistaSelect = document.getElementById('almacenista_id');

    function actualizarCamposPorRol() {
        if (!rolSelect) {
            return;
        }

        const selectedOption = rolSelect.options[rolSelect.selectedIndex];
        const tipoRol = selectedOption ? selectedOption.dataset.roleType : 'otro';

        const esAlmacenista = tipoRol === 'almacenista';
        const esSupervisor = tipoRol === 'supervisor';

        if (bodegaWrap && bodegaSelect) {
            bodegaWrap.classList.toggle('hidden', !esAlmacenista);
            bodegaSelect.disabled = !esAlmacenista;
            bodegaSelect.required = esAlmacenista;

            if (!esAlmacenista) {
                bodegaSelect.value = '';
            }
        }

        if (almacenistaWrap && almacenistaSelect) {
            almacenistaWrap.classList.toggle('hidden', !esSupervisor);
            almacenistaSelect.disabled = !esSupervisor;
            almacenistaSelect.required = esSupervisor;

            if (!esSupervisor) {
                almacenistaSelect.value = '';
            }
        }
    }

    if (rolSelect) {
        rolSelect.addEventListener('change', actualizarCamposPorRol);
        actualizarCamposPorRol();
    }
});
</script>
@endsection