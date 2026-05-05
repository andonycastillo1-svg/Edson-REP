<button {{ $attributes->merge(['type' => 'button', 'class' => 'ui-btn-secondary disabled:opacity-60']) }}>
    {{ $slot }}
</button>
