@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
])

@php
    $anchos = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
@endphp

<div x-data="{ show: @js($show) }"
    x-effect="document.body.classList.toggle('overflow-y-hidden', show)"
    x-on:open-modal.window="$event.detail === @js($name) && (show = true)"
    x-on:close-modal.window="$event.detail === @js($name) && (show = false)"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    data-ui-dialogo role="dialog" aria-modal="true" aria-label="{{ $attributes->get('aria-label', 'Confirmación') }}"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6"
    @unless($show) style="display:none" @endunless>
    <div class="fixed inset-0 bg-slate-900/60" x-on:click="show = false" aria-hidden="true"></div>
    <div class="ui-modal relative mx-auto w-full {{ $anchos[$maxWidth] ?? $anchos['2xl'] }}">
        {{ $slot }}
    </div>
</div>
