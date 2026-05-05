@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-sky-200 bg-white px-4 py-2.5 text-slate-800 shadow-sm focus:border-blue-500 focus:ring-blue-500']) }}>
