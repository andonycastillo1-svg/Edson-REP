@props(['titulo', 'descripcion' => null])

<header {{ $attributes->class(['ui-page-header', 'ui-page-header-body']) }}>
    <div class="min-w-0">
        <h1 class="ui-page-title">{{ $titulo }}</h1>
        @if($descripcion)<p class="ui-page-subtitle">{{ $descripcion }}</p>@endif
    </div>
    @if($slot->isNotEmpty())
        <div class="ui-action-bar">{{ $slot }}</div>
    @endif
</header>
