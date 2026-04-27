@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
@php
    $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
@endphp
<div class="ui-panel w-full max-w-6xl p-6 md:p-8">

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ session('error') }}
        </div>
    @endif


    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Usuarios</h1>
            <p class="text-sm text-gray-500">Gestión de usuarios del sistema</p>
        </div>

        <a href="{{ route($routePrefix . '.usuarios.create') }}"
           class="ui-btn-success">
            + Nuevo usuario
        </a>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto ui-table">
        <table class="min-w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-5 py-3">ID</th>
                    <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-5 py-3">Nombre</th>
                    <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-5 py-3">Correo</th>
                    <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-5 py-3">Rol</th>
                    <th class="text-right text-xs font-semibold text-gray-600 uppercase tracking-wider px-5 py-3">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @if($usuarios->count() > 0)
                    @foreach($usuarios as $u)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-5 py-4 text-sm text-gray-700">{{ $u->id }}</td>

                            <td class="px-5 py-4">
                                <div class="text-sm font-medium text-gray-800">
                                    {{ $u->name ?? $u->nombre ?? '—' }}
                                </div>
                            </td>

                            <td class="px-5 py-4 text-sm text-gray-700">
                                {{ $u->email ?? '—' }}
                            </td>

                            <td class="px-5 py-4">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                    {{ $u->role->nombre ?? 'Sin rol' }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    @if(auth()->user()->role_id != 4 || (int) optional(optional($u->creator)->role)->id === 4)
                                        <a href="{{ route($routePrefix . '.usuarios.edit', $u->id) }}"
                                           class="ui-btn-edit">
                                            Editar
                                        </a>

                                        <form action="{{ route($routePrefix . '.usuarios.destroy', $u->id) }}" method="POST"
                                              onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="ui-btn-delete">
                                                Eliminar
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">Solo lectura (creado por Admin)</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">
                            No hay usuarios registrados.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div class="mt-6 flex items-center justify-between text-sm text-gray-500">
        <a href="{{ route($routePrefix . '.dashboard') }}" class="text-blue-600 hover:underline">← Volver al menú</a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-red-600 hover:underline">Cerrar sesión</button>
        </form>
    </div>

</div>
@endsection
