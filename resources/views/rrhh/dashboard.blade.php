@extends('layouts.portal')

@section('title', 'RRHH - Inicio')

@section('content')
<div class="w-full">

    <x-dashboard-header
        title="Recursos Humanos"
        description="Gestiona al personal, las cuentas de usuario y las alertas de reemplazo del sistema."
    />

    <section>

        <div class="mb-5">
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-blue-600">
                Módulos
            </p>

            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                Herramientas disponibles
            </h2>

            <p class="mt-1 text-sm font-medium text-slate-500">
                Selecciona el módulo que deseas administrar.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">

            <x-dashboard-card
                :href="route('rrhh.colaboradores.index')"
                title="Colaboradores"
                description="Administra altas, bajas y actualización de información del personal."
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
                :href="route('rrhh.usuarios.index')"
                title="Usuarios"
                description="Administra cuentas, accesos y permisos de los usuarios."
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
                :href="route('rrhh.alertas.index')"
                title="Alertas"
                :description="($totalAlertas ?? 0) . ' alerta(s) pendiente(s) de revisión.'"
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
                            fill-rule="evenodd"
                            d="M9.401 3.003c1.155-2.004 4.043-2.004 5.198 0l7.355 12.75c1.154 2-.289 4.497-2.599 4.497H4.645c-2.31 0-3.753-2.497-2.599-4.497l7.355-12.75zM12 8.25a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 9a.75.75 0 100-1.5.75.75 0 000 1.5z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </x-slot:icon>
            </x-dashboard-card>

        </div>
    </section>

</div>
@endsection