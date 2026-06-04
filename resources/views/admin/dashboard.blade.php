@extends('layouts.admin')

@section('title', 'Admin - Inicio')

@section('content')
<div class="mx-auto w-full max-w-6xl">

    <div class="ui-page-header">
        <div class="ui-page-header-body">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-sky-100 bg-white shadow-sm">
                    <img src="{{ asset('img/logo1.png') }}" alt="Logo" class="h-11 w-11 object-contain">
                </div>

                <div>
                    <span class="ui-kicker">Panel principal</span>
                    <h1 class="ui-page-title">Panel administrativo</h1>
                    <p class="ui-page-subtitle">
                        Bienvenido, <span class="font-bold text-slate-700">{{ auth()->user()->name }}</span>. Gestiona usuarios, bodegas, compras, colaboradores, asignaciones y vehículos.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('admin.usuarios.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-xl text-blue-700">👤</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Usuarios</h2>
                        <p class="mt-1 text-sm text-slate-500">Gestiona accesos y roles</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('admin.bodegas.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-xl text-sky-700">🏬</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Bodegas</h2>
                        <p class="mt-1 text-sm text-slate-500">Consulta y administra bodegas</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('admin.compras.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-xl text-indigo-700">🧾</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Compras</h2>
                        <p class="mt-1 text-sm text-slate-500">Registra y revisa compras</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('admin.colaboradores.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700">🧑‍🤝‍🧑</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Colaboradores</h2>
                        <p class="mt-1 text-sm text-slate-500">Personal y detalle de asignaciones</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('admin.asignaciones.create') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-xl text-amber-700">📦</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Asignaciones</h2>
                        <p class="mt-1 text-sm text-slate-500">Entrega de inventario</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('admin.vehiculos.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-xl text-rose-700">🚚</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Vehículos</h2>
                        <p class="mt-1 text-sm text-slate-500">Control de flota</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('admin.vehiculos.productos.index') }}" class="dashboard-card group md:col-span-2 xl:col-span-1">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-50 text-xl text-cyan-700">🧰</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Productos del vehículo</h2>
                        <p class="mt-1 text-sm text-slate-500">Refacciones por unidad</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>
    </div>

    <div class="ui-panel mt-5 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-500">Sesión activa como administrador.</p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="ui-btn-danger text-xs">Cerrar sesión</button>
            </form>
        </div>
    </div>

</div>
@endsection
