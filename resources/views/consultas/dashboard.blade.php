@extends('layouts.admin')

@section('title', 'RRHH - Inicio')

@section('content')
<div class="ui-panel w-full max-w-5xl overflow-hidden">
    <div class="ui-section-header from-emerald-700 to-teal-700">
        <p class="text-xs font-bold uppercase tracking-[0.24em] text-emerald-100">Recursos Humanos</p>
        <h1 class="mt-2 text-3xl font-black text-white">Panel RRHH</h1>
        <p class="mt-1 text-sm font-medium text-emerald-50">Gestión de personal, cuentas y alertas por reemplazo antes de vida útil.</p>
    </div>

    <div class="grid gap-4 bg-slate-50/80 p-6">
        <a href="{{ route('rrhh.colaboradores.index') }}" class="ui-card group p-5 hover:border-emerald-300 hover:bg-emerald-50/70">
            <div class="font-bold text-slate-900">🧑‍💼 Colaboradores</div>
            <div class="mt-1 text-sm text-slate-600">Altas, bajas y gestión de personal</div>
        </a>

        <a href="{{ route('rrhh.usuarios.index') }}" class="ui-card group p-5 hover:border-emerald-300 hover:bg-emerald-50/70">
            <div class="font-bold text-slate-900">👥 Usuarios</div>
            <div class="mt-1 text-sm text-slate-600">Cuentas y permisos</div>
        </a>

        <div class="rounded-2xl border border-amber-200 bg-amber-50/90 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-bold text-amber-900">⚠️ Alertas de reemplazo con descuento potencial</h2>
                <span class="rounded-full bg-amber-200 px-3 py-1 text-xs font-bold text-amber-950">{{ $alertas->count() }}</span>
            </div>

            @if($alertas->isEmpty())
                <p class="mt-2 text-sm text-amber-700">No hay alertas pendientes por ahora.</p>
            @else
                <div class="mt-4 overflow-x-auto rounded-xl border border-amber-200 bg-white/70">
                    <table class="min-w-full text-xs">
                        <thead class="bg-amber-100/80">
                            <tr class="text-left text-amber-950">
                                <th class="px-3 py-2">Colaborador</th>
                                <th class="px-3 py-2">Producto</th>
                                <th class="px-3 py-2">Asignación</th>
                                <th class="px-3 py-2">Daño/Reemplazo</th>
                                <th class="px-3 py-2">Vida útil</th>
                                <th class="px-3 py-2">Restante</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alertas as $a)
                                <tr class="border-t border-amber-100 text-amber-950">
                                    <td class="px-3 py-2">{{ $a->colaborador_codigo }}</td>
                                    <td class="px-3 py-2">{{ $a->producto_codigo }}</td>
                                    <td class="px-3 py-2">{{ optional($a->fecha_asignacion_anterior)->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ optional($a->fecha_dano_reemplazo)->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $a->vida_util_meses }} meses</td>
                                    <td class="px-3 py-2 font-bold">{{ $a->meses_restantes }} meses</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="pt-4 text-center border-t border-slate-200">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="ui-btn-secondary text-rose-700 hover:bg-rose-50">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection
