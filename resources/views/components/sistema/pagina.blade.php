@props(['titulo', 'volver' => null])
<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $titulo }}</h1>
            <a href="{{ $volver ?? route('dashboard') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Volver</a>
        </div>
        @if(session('ok'))
            <p role="status" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('ok') }}</p>
        @endif
        @if($errors->any())
            <ul role="alert" class="list-disc rounded-xl bg-red-50 py-3 pl-8 text-sm text-red-900">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        {{ $slot }}
    </div>
</x-app-layout>
