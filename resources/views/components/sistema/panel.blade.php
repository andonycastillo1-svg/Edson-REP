@props(['titulo' => null])
<section {{ $attributes->class(['rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6']) }}>
    @if($titulo)<h2 class="mb-4 text-lg font-bold text-slate-900">{{ $titulo }}</h2>@endif
    {{ $slot }}
</section>
