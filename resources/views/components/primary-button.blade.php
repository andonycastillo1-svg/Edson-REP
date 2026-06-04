<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-btn-primary text-xs uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
