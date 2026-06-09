@auth
    <style>[x-cloak] { display: none !important; }</style>
    @php
        $notificacionesDisponibles = \Illuminate\Support\Facades\Schema::hasTable('notifications');
        $notificacionesNoLeidas = $notificacionesDisponibles
            ? auth()->user()->unreadNotifications()->count()
            : 0;
        $notificacionesRecientes = $notificacionesDisponibles
            ? auth()->user()->notifications()->latest()->take(5)->get()
            : collect();
    @endphp

    <div x-data="{ notificacionesAbiertas: false }" class="relative">
        <button type="button"
                @click="notificacionesAbiertas = !notificacionesAbiertas"
                @keydown.escape.window="notificacionesAbiertas = false"
                class="relative inline-flex h-11 w-11 items-center justify-center rounded-full border border-sky-200 bg-white text-slate-700 shadow-sm transition hover:bg-sky-50 focus:outline-none focus:ring-4 focus:ring-sky-200"
                aria-label="Abrir notificaciones"
                :aria-expanded="notificacionesAbiertas.toString()">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 10-12 0v.75a8.967 8.967 0 01-2.312 6.022 23.848 23.848 0 005.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>

            @if($notificacionesNoLeidas > 0)
                <span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
                    {{ $notificacionesNoLeidas > 99 ? '99+' : $notificacionesNoLeidas }}
                </span>
            @endif
        </button>

        <div x-cloak
             x-show="notificacionesAbiertas"
             x-transition.origin.top.right
             @click.outside="notificacionesAbiertas = false"
             class="fixed inset-x-2 top-16 z-50 max-h-[75vh] max-w-[calc(100vw-1rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-auto sm:right-0 sm:top-12 sm:w-96 sm:max-w-[calc(100vw-2rem)]">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <div>
                    <p class="font-bold text-slate-900">Notificaciones</p>
                    <p class="text-xs text-slate-500">{{ $notificacionesNoLeidas }} sin leer</p>
                </div>

                @if($notificacionesNoLeidas > 0)
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="text-xs font-semibold text-blue-700 hover:underline">
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
                                <p class="truncate text-sm font-bold text-slate-900">{{ $data['titulo'] ?? 'Notificación' }}</p>
                                <p class="mt-1 line-clamp-2 break-words text-xs text-slate-600">{{ $data['mensaje'] ?? '' }}</p>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span class="text-[11px] text-slate-400">{{ $notificacion->created_at?->diffForHumans() }}</span>
                                    @if($urlSegura)
                                        <a href="{{ $url }}" class="text-xs font-semibold text-blue-700 hover:underline">Abrir</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">No tienes notificaciones.</div>
                @endforelse
            </div>

            <a href="{{ route('notificaciones.index') }}"
               class="block border-t border-slate-100 bg-slate-50 px-4 py-3 text-center text-sm font-bold text-blue-700 transition hover:bg-slate-100">
                Ver todas
            </a>
        </div>
    </div>
@endauth
