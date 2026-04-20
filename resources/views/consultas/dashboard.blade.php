@extends('layouts.admin')

@section('title', 'RRHH - Inicio')

@section('content')
<div class="w-full max-w-4xl rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
    <div class="bg-emerald-700 px-6 py-5 text-white">
        <h1 class="text-2xl font-bold">Panel RRHH</h1>
        <p class="text-sm text-emerald-100">Gestión de personal, cuentas y alertas por reemplazo antes de vida útil.</p>
    </div>

    <div class="p-6 grid gap-3 bg-slate-50">
        <a href="{{ route('rrhh.colaboradores.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 hover:border-emerald-300 hover:bg-emerald-50">
            <div class="font-semibold">🧑‍💼 Colaboradores</div>
            <div class="text-xs text-slate-500">Altas, bajas y gestión de personal</div>
        </a>

        <a href="{{ route('rrhh.usuarios.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 hover:border-emerald-300 hover:bg-emerald-50">
            <div class="font-semibold">👥 Usuarios</div>
            <div class="text-xs text-slate-500">Cuentas y permisos</div>
        </a>

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-semibold text-amber-800">⚠️ Alertas de reemplazo con descuento potencial</h2>
                <span class="rounded-full bg-amber-200 px-2 py-1 text-xs font-semibold text-amber-900">{{ ($alertas ?? collect())->count() }}</span>
            </div>

            @if(($alertas ?? collect())->isEmpty())
                <p class="mt-2 text-sm text-amber-700">No hay alertas pendientes por ahora.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-amber-900">
                                <th class="py-1 pr-3">Colaborador</th>
                                <th class="py-1 pr-3">Producto</th>
                                <th class="py-1 pr-3">Asignación</th>
                                <th class="py-1 pr-3">Daño/Reemplazo</th>
                                <th class="py-1 pr-3">Vida útil</th>
                                <th class="py-1 pr-3">Restante</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($alertas ?? collect()) as $a)
                                <tr class="border-t border-amber-200 text-amber-900">
                                    <td class="py-1 pr-3">{{ $a->colaborador_codigo }}</td>
                                    <td class="py-1 pr-3">{{ $a->producto_codigo }}</td>
                                    <td class="py-1 pr-3">{{ optional($a->fecha_asignacion_anterior)->format('d/m/Y') }}</td>
                                    <td class="py-1 pr-3">{{ optional($a->fecha_dano_reemplazo)->format('d/m/Y') }}</td>
                                    <td class="py-1 pr-3">{{ $a->vida_util_meses }} meses</td>
                                    <td class="py-1 pr-3 font-semibold">{{ $a->meses_restantes }} meses</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="pt-3 text-center border-t border-slate-200">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-medium text-rose-600 hover:underline">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection
