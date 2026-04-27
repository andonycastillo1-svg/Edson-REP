<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-btn-danger text-xs uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
