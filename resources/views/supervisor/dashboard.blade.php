@extends('layouts.operador')

@section('title', 'Supervisor - Inicio')

@section('content')
<div class="mx-auto w-full max-w-5xl">
    <div class="ui-panel px-6 py-7">
        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-blue-600">Supervisor</p>
        <h1 class="mt-2 text-2xl font-extrabold text-slate-950">Panel de supervisión</h1>
        <p class="mt-2 text-sm text-slate-500">
            Consulta las asignaciones creadas por los almacenistas relacionados y carga sus evidencias de entrega.
        </p>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2">
        <a href="{{ route('supervisor.asignaciones.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-xl text-amber-700">📦</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Asignaciones relacionadas</h2>
                        <p class="mt-1 text-sm text-slate-500">Revisar pendientes y subir evidencias</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>
    </div>

    <div class="ui-panel mt-5 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-500">Sesión activa como Supervisor.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="ui-btn-danger text-xs">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection
