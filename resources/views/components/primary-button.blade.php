<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-btn-success text-xs uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
