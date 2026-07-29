@props([
    'title',
    'description' => null,
    'kicker' => 'Panel principal',
])

<section
    {{ $attributes->class([
        'mb-9 border-b border-slate-200 pb-7',
    ]) }}
>
    <div class="max-w-4xl">

        <span class="text-xs font-extrabold uppercase tracking-[0.18em] text-blue-600">
            {{ $kicker }}
        </span>

        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">
            {{ $title }}
        </h1>

        @if ($description)
            <p class="mt-3 text-sm font-medium leading-6 text-slate-600 sm:text-base">
                Bienvenido,
                <span class="font-extrabold text-slate-800">
                    {{ auth()->user()->name }}
                </span>.
                {{ $description }}
            </p>
        @endif

    </div>
</section>