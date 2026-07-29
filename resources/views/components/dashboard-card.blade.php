@props([
    'href',
    'title',
    'description' => null,
    'tone' => 'blue',
])

@php
    $tones = [
        'blue' => [
            'accent' => 'bg-blue-600',
            'icon' => 'bg-blue-50 text-blue-700',
            'hover' => 'hover:border-blue-300',
            'link' => 'text-blue-700',
        ],
        'sky' => [
            'accent' => 'bg-sky-600',
            'icon' => 'bg-sky-50 text-sky-700',
            'hover' => 'hover:border-sky-300',
            'link' => 'text-sky-700',
        ],
        'indigo' => [
            'accent' => 'bg-indigo-600',
            'icon' => 'bg-indigo-50 text-indigo-700',
            'hover' => 'hover:border-indigo-300',
            'link' => 'text-indigo-700',
        ],
        'emerald' => [
            'accent' => 'bg-emerald-600',
            'icon' => 'bg-emerald-50 text-emerald-700',
            'hover' => 'hover:border-emerald-300',
            'link' => 'text-emerald-700',
        ],
        'amber' => [
            'accent' => 'bg-amber-500',
            'icon' => 'bg-amber-50 text-amber-700',
            'hover' => 'hover:border-amber-300',
            'link' => 'text-amber-700',
        ],
        'rose' => [
            'accent' => 'bg-rose-600',
            'icon' => 'bg-rose-50 text-rose-700',
            'hover' => 'hover:border-rose-300',
            'link' => 'text-rose-700',
        ],
        'cyan' => [
            'accent' => 'bg-cyan-600',
            'icon' => 'bg-cyan-50 text-cyan-700',
            'hover' => 'hover:border-cyan-300',
            'link' => 'text-cyan-700',
        ],
        'violet' => [
            'accent' => 'bg-violet-600',
            'icon' => 'bg-violet-50 text-violet-700',
            'hover' => 'hover:border-violet-300',
            'link' => 'text-violet-700',
        ],
    ];

    $selectedTone = $tones[$tone] ?? $tones['blue'];
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'group relative flex min-h-[190px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm',
        'transition duration-200 hover:-translate-y-1 hover:shadow-xl',
        $selectedTone['hover'],
    ]) }}
>
    <span
        class="absolute inset-x-0 top-0 h-1 {{ $selectedTone['accent'] }}"
        aria-hidden="true"
    ></span>

    <div class="flex items-start justify-between gap-4">

        <span
            class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl transition duration-200 group-hover:scale-105 {{ $selectedTone['icon'] }}"
        >
            {{ $icon ?? '' }}
        </span>

        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-lg text-slate-400 transition group-hover:translate-x-1 group-hover:bg-slate-900 group-hover:text-white">
            →
        </span>

    </div>

    <div class="mt-5">

        <h2 class="text-xl font-extrabold tracking-tight text-slate-950">
            {{ $title }}
        </h2>

        @if ($description)
            <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                {{ $description }}
            </p>
        @endif

    </div>

    <p class="mt-auto pt-5 text-sm font-extrabold {{ $selectedTone['link'] }}">
        Abrir módulo
    </p>
</a>