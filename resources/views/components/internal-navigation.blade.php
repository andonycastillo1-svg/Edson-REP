@props(['backUrl'])

<div {{ $attributes->class(['mb-4 flex flex-wrap items-center justify-end gap-2']) }}>
    <a href="{{ $backUrl }}"
       class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
        ← Volver
    </a>
</div>
