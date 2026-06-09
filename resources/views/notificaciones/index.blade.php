@extends('layouts.app')

@section('content')
<div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <x-internal-navigation :back-url="route('dashboard')" />

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <div class="flex flex-col gap-4 border-b border-slate-200 bg-gradient-to-r from-white to-blue-50 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-blue-600">Centro de avisos</p>
                <h1 class="mt-1 text-2xl font-extrabold text-slate-950">Notificaciones</h1>
                <p class="mt-1 text-sm text-slate-500">Consulta y administra los avisos asociados a tu usuario.</p>
            </div>

            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                <form method="POST" action="{{ route('notificaciones.leer-todas') }}" class="w-full sm:w-auto">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                        Marcar todas como leídas
                    </button>
                </form>

                <form method="POST" action="{{ route('notificaciones.eliminar-todas') }}" class="w-full sm:w-auto" onsubmit="return confirm('¿Eliminar todas tus notificaciones? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                        Eliminar todas
                    </button>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="mx-5 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 sm:mx-7">
                {{ session('success') }}
            </div>
        @endif

        <div class="divide-y divide-slate-100">
            @forelse($notificaciones as $notificacion)
                @php
                    $data = $notificacion->data;
                    $url = $data['url'] ?? null;
                    $urlSegura = is_string($url) && \Illuminate\Support\Str::startsWith($url, [url('/'), '/']);
                @endphp

                <article class="px-5 py-5 sm:px-7 {{ $notificacion->read_at ? 'bg-white' : 'bg-blue-50/60' }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <span class="mt-2 h-3 w-3 shrink-0 rounded-full {{ $notificacion->read_at ? 'bg-slate-300' : 'bg-blue-600' }}"></span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-bold text-slate-900">{{ $data['titulo'] ?? 'Notificación' }}</h2>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $notificacion->read_at ? 'bg-slate-100 text-slate-600' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $notificacion->read_at ? 'Leída' : 'No leída' }}
                                    </span>
                                </div>

                                <p class="mt-2 break-words text-sm leading-6 text-slate-600">{{ $data['mensaje'] ?? '' }}</p>

                                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span>Tipo: {{ str_replace('_', ' ', $data['tipo'] ?? 'general') }}</span>
                                    <time datetime="{{ $notificacion->created_at?->toIso8601String() }}">
                                        {{ $notificacion->created_at?->format('d/m/Y H:i') }}
                                    </time>
                                </div>
                            </div>
                        </div>

                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:shrink-0 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                            @if($urlSegura)
                                <a href="{{ $url }}" class="inline-flex w-full sm:w-auto items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-blue-700">
                                    Abrir
                                </a>
                            @endif

                            @if(!$notificacion->read_at)
                                <form method="POST" action="{{ route('notificaciones.leer', $notificacion->id) }}" class="w-full sm:w-auto">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                                        Marcar como leída
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('notificaciones.eliminar', $notificacion->id) }}" class="w-full sm:w-auto" onsubmit="return confirm('¿Eliminar esta notificación?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-50">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">🔔</div>
                    <p class="mt-4 font-semibold text-slate-700">No tienes notificaciones.</p>
                </div>
            @endforelse
        </div>

        @if($notificaciones->hasPages())
            <div class="border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-7">
                {{ $notificaciones->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
