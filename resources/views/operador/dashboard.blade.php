@extends('layouts.portal')

@section('title', 'Operador - Inicio')

@section('content')
<div class="w-full">

    <x-dashboard-header
        title="Panel de operaciones"
        description="Administra el inventario, las compras, las asignaciones y los traslados entre bodegas."
    />

    <section>

        <div class="mb-5">
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-blue-600">
                Módulos
            </p>

            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                Herramientas operativas
            </h2>

            <p class="mt-1 text-sm font-medium text-slate-500">
                Selecciona el módulo que deseas utilizar.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

            <x-dashboard-card
                :href="route('operador.bodegas.index')"
                title="Bodegas"
                description="Consulta el inventario disponible en cada bodega."
                tone="sky"
            >
                <x-slot:icon>
                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-4.5v-6h-7v6H4a1 1 0 01-1-1v-9.5z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

            <x-dashboard-card
                :href="route('operador.compras.index')"
                title="Compras"
                description="Registra, consulta y administra las compras."
                tone="indigo"
            >
                <x-slot:icon>
                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M4.5 3.75A2.25 2.25 0 016.75 1.5h7.69c.597 0 1.17.237 1.591.659l3.31 3.31c.422.421.659.994.659 1.591v13.19a2.25 2.25 0 01-2.25 2.25h-11A2.25 2.25 0 014.5 20.25V3.75zM8.25 11.25a.75.75 0 000 1.5h7.5a.75.75 0 000-1.5h-7.5zm0 4a.75.75 0 000 1.5h7.5a.75.75 0 000-1.5h-7.5z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

            <x-dashboard-card
                :href="route('operador.asignaciones.create')"
                title="Asignaciones"
                description="Registra la entrega de productos y equipo a los colaboradores."
                tone="amber"
            >
                <x-slot:icon>
                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path d="M12 2.25l8.25 4.5v10.5L12 21.75l-8.25-4.5V6.75L12 2.25zm0 1.71L6.06 7.2 12 10.44l5.94-3.24L12 3.96zm-6.75 4.5v7.9l6 3.27v-7.9l-6-3.27zm7.5 11.17l6-3.27v-7.9l-6 3.27v7.9z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

            <x-dashboard-card
                :href="route('operador.operaciones.traslados.index')"
                title="Traslados"
                description="Consulta y gestiona las solicitudes de traslado entre bodegas."
                tone="emerald"
            >
                <x-slot:icon>
                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path d="M7.28 3.22a.75.75 0 011.06 1.06L6.62 6h10.63a.75.75 0 010 1.5H6.62l1.72 1.72a.75.75 0 11-1.06 1.06l-3-3a.75.75 0 010-1.06l3-3zM16.72 13.72a.75.75 0 011.06 0l3 3a.75.75 0 010 1.06l-3 3a.75.75 0 11-1.06-1.06L18.44 18H7.75a.75.75 0 010-1.5h10.69l-1.72-1.72a.75.75 0 010-1.06z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

        </div>

    </section>

</div>
@endsection