@extends('layouts.portal')

@section('title', 'Administrador - Inicio')

@section('content')
<div class="w-full">

    <x-dashboard-header
        title="Panel administrativo"
        description="Gestiona usuarios, bodegas, compras, colaboradores, asignaciones, vehículos y los recursos generales del sistema."
    />

    <section>

        <div class="mb-5">
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-blue-600">
                Módulos
            </p>

            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                Herramientas administrativas
            </h2>

            <p class="mt-1 text-sm font-medium text-slate-500">
                Selecciona el módulo que deseas administrar.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">

            <x-dashboard-card
                :href="route('admin.usuarios.index')"
                title="Usuarios"
                description="Administra cuentas, accesos y roles del sistema."
                tone="blue"
            >
                <x-slot:icon>
                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path d="M6.75 6a3 3 0 116 0 3 3 0 01-6 0zM2.25 18a6 6 0 0112 0v.75h-12V18z"/>
                        <path d="M15.75 7.5A2.25 2.25 0 1118 9.75a2.25 2.25 0 01-2.25-2.25zM15 18a7.47 7.47 0 00-1.113-3.933A4.5 4.5 0 0121.75 17v.75H15V18z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

            <x-dashboard-card
                :href="route('admin.bodegas.index')"
                title="Bodegas"
                description="Consulta inventario y administra las bodegas."
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
                :href="route('admin.compras.index')"
                title="Compras"
                description="Registra, consulta y da seguimiento a las compras."
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
                :href="route('admin.colaboradores.index')"
                title="Colaboradores"
                description="Gestiona al personal y consulta sus asignaciones."
                tone="emerald"
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
                            d="M8.25 6.75a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM4.5 20.118a7.5 7.5 0 0115 0 .632.632 0 01-.326.557A13.448 13.448 0 0112 22.5a13.448 13.448 0 01-7.174-1.825.632.632 0 01-.326-.557z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

            <x-dashboard-card
                :href="route('admin.asignaciones.create')"
                title="Asignaciones"
                description="Registra la entrega de productos a los colaboradores."
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
                :href="route('admin.vehiculos.index')"
                title="Vehículos"
                description="Administra las unidades y consulta el control de flota."
                tone="rose"
            >
                <x-slot:icon>
                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path d="M3 5.25A2.25 2.25 0 015.25 3h9A2.25 2.25 0 0116.5 5.25v2.5h1.879a2.25 2.25 0 011.768.856l1.371 1.714c.319.398.482.893.482 1.403v4.527a1.5 1.5 0 01-1.5 1.5h-.879a3 3 0 01-5.742 0H9.121a3 3 0 01-5.742 0H3.75A.75.75 0 013 17V5.25z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

            <x-dashboard-card
                :href="route('admin.vehiculos.productos.index')"
                title="Productos del vehículo"
                description="Administra las refacciones y productos asignados por unidad."
                tone="cyan"
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
                            d="M8.25 3a2.25 2.25 0 00-2.25 2.25v1.5H4.5A2.25 2.25 0 002.25 9v9.75A2.25 2.25 0 004.5 21h15a2.25 2.25 0 002.25-2.25V9a2.25 2.25 0 00-2.25-2.25H18v-1.5A2.25 2.25 0 0015.75 3h-7.5zM16.5 6.75v-1.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 00-.75.75v1.5h9z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

        </div>

    </section>

</div>
@endsection