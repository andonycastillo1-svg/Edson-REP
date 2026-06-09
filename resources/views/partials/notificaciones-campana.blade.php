@auth
    <style>
        [x-cloak] { 
            display: none !important; 
        }

        .notif-bell-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            padding: 8px 12px;
            border-radius: 9999px;
            border: 1px solid #bae6fd;
            background: #ffffff;
            color: #334155;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            transition: background .15s ease, box-shadow .15s ease;
        }

        .notif-bell-button:hover {
            background: #f0f9ff;
        }

        .notif-badge-number {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-width: 22px !important;
            height: 22px !important;
            padding: 0 7px !important;
            border-radius: 9999px !important;
            background: #dc2626 !important;
            color: #ffffff !important;
            font-size: 12px !important;
            font-weight: 900 !important;
            line-height: 1 !important;
            text-align: center !important;
            box-shadow: 0 4px 10px rgba(220, 38, 38, .35) !important;
        }
    </style>

    @php
        $notificacionesDisponibles = \Illuminate\Support\Facades\Schema::hasTable('notifications');

        $notificacionesNoLeidas = $notificacionesDisponibles
            ? auth()->user()->unreadNotifications()->count()
            : 0;

        $notificacionesRecientes = $notificacionesDisponibles
            ? auth()->user()->notifications()->latest()->take(5)->get()
            : collect();

        $badgeNotificaciones = $notificacionesNoLeidas > 99 ? '99+' : (string) $notificacionesNoLeidas;
    @endphp

    <div x-data="{ notificacionesAbiertas: false }" class="relative">
        <button type="button"
                @click="notificacionesAbiertas = !notificacionesAbiertas"
                @keydown.escape.window="notificacionesAbiertas = false"
                class="notif-bell-button"
                aria-label="Abrir notificaciones"
                :aria-expanded="notificacionesAbiertas.toString()">

            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.8"
                      d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 10-12 0v.75a8.967 8.967 0 01-2.312 6.022 23.848 23.848 0 005.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>

            @if($notificacionesNoLeidas > 0)
                <span class="notif-badge-number">
                    {{ $badgeNotificaciones }}
                </span>
            @endif
        </button>

        <div x-cloak
             x-show="notificacionesAbiertas"
             x-transition.origin.top.right
             @click.outside="notificacionesAbiertas = false"
             class="fixed inset-x-3 top-16 z-50 max-h-[75vh] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-auto sm:right-0 sm:top-12 sm:w-96 sm:max-w-[calc(100vw-2rem)]">

            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div class="min-w-0">
                    <p class="font-bold text-slate-900">Notificaciones</p>
                    <p class="text-xs text-slate-500">
                        {{ $notificacionesNoLeidas }} sin leer
                    </p>
                </div>

                @if($notificacionesNoLeidas > 0)
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="whitespace-nowrap rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700">
                            Marcar todas
                        </button>
                    </form>
                @endif
            </div>

            <div class="max-h-[55vh] divide-y divide-slate-100 overflow-y-auto">
                @forelse($notificacionesRecientes as $notificacion)
                    @php
                        $data = $notificacion->data;
                        $url = $data['url'] ?? null;
                        $urlSegura = is_string($url) && \Illuminate\Support\Str::startsWith($url, [url('/'), '/']);
                    @endphp

                    <div class="px-4 py-3 {{ $notificacion->read_at ? 'bg-white' : 'bg-blue-50/70' }}">
                        <div class="flex gap-3">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $notificacion->read_at ? 'bg-slate-300' : 'bg-blue-600' }}"></span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-900">
                                    {{ $data['titulo'] ?? 'Notificación' }}
                                </p>

                                <p class="mt-1 line-clamp-2 text-xs text-slate-600">
                                    {{ $data['mensaje'] ?? '' }}
                                </p>

                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span class="text-[11px] text-slate-400">
                                        {{ $notificacion->created_at?->diffForHumans() }}
                                    </span>

                                    @if($urlSegura)
                                        <a href="{{ $url }}"
                                           class="text-xs font-semibold text-blue-700 hover:underline">
                                            Abrir
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">
                        No tienes notificaciones.
                    </div>
                @endforelse
            </div>

            <a href="{{ route('notificaciones.index') }}"
               class="block border-t border-slate-100 bg-slate-50 px-4 py-3 text-center text-sm font-bold text-blue-700 transition hover:bg-slate-100">
                Ver todas
            </a>
        </div>
    </div>
@endauth