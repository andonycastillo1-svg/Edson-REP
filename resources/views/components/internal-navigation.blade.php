@props(['backUrl'])

<div {{ $attributes->class(['mb-4 flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end']) }}>
    <a href="{{ $backUrl }}"
       class="inline-flex w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
        ← Volver
    </a>

    <a href="{{ route('dashboard') }}"
       class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-blue-700 sm:w-auto">
        Menú principal
    </a>
</div>
