@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-xl border-l-4 border-sky-500 bg-sky-50 ps-3 pe-4 py-2 text-start text-base font-semibold text-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-200 transition duration-150 ease-in-out'
            : 'block w-full rounded-xl border-l-4 border-transparent ps-3 pe-4 py-2 text-start text-base font-semibold text-slate-600 hover:border-sky-200 hover:bg-sky-50 hover:text-sky-900 focus:outline-none focus:ring-2 focus:ring-sky-200 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
