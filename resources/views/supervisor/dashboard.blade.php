@extends('layouts.portal')

@section('title', 'Supervisor - Inicio')

@section('content')
<div class="w-full">

    <x-dashboard-header
        title="Panel de supervisión"
        description="Consulta las asignaciones relacionadas con los almacenistas y administra las evidencias de entrega."
    />

    <section>

        <div class="mb-5">
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-blue-600">
                Módulos
            </p>

            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                Herramientas de supervisión
            </h2>

            <p class="mt-1 text-sm font-medium text-slate-500">
                Selecciona el módulo que deseas consultar.
            </p>
        </div>

        <div class="grid max-w-2xl grid-cols-1 gap-5">

            <x-dashboard-card
                :href="route('supervisor.asignaciones.index')"
                title="Asignaciones relacionadas"
                description="Revisa las asignaciones pendientes y carga las evidencias correspondientes a cada entrega."
                tone="amber"
            >
                <x-slot:icon>
                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path
                            d="M12 2.25l8.25 4.5v10.5L12 21.75l-8.25-4.5V6.75L12 2.25zm0 1.71L6.06 7.2 12 10.44l5.94-3.24L12 3.96zm-6.75 4.5v7.9l6 3.27v-7.9l-6-3.27zm7.5 11.17l6-3.27v-7.9l-6 3.27v7.9z"
                        />
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

        </div>

    </section>

</div>
@endsection