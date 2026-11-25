@auth
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
                class="ui-control-icon w-auto gap-2 px-2.5"
                aria-label="Abrir notificaciones{{ $notificacionesNoLeidas ? ' · '.$notificacionesNoLeidas.' sin leer' : '' }}"
                :aria-expanded="notificacionesAbiertas.toString()">

            <x-ui.icono nombre="campana" />

            @if($notificacionesNoLeidas > 0)
                <x-ui.estado variante="informacion" aria-hidden="true">{{ $badgeNotificaciones }}</x-ui.estado>
            @endif
        </button>

        <div x-cloak
             x-show="notificacionesAbiertas"
             x-transition.origin.top.right
             @click.outside="notificacionesAbiertas = false"
             class="ui-modal fixed inset-x-3 top-16 z-50 max-h-[75vh] overflow-hidden sm:absolute sm:inset-auto sm:right-0 sm:top-12 sm:w-96 sm:max-w-[calc(100vw-2rem)]">

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
                                class="ui-btn-primary whitespace-nowrap">
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
