@props(['titulo' => null])
<section {{ $attributes->class(['ui-panel p-4 sm:p-5']) }}>
    @if($titulo)<h2 class="mb-3 text-base font-bold text-slate-900">{{ $titulo }}</h2>@endif
    {{ $slot }}
</section>
