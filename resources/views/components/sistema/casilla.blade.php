@props(['name', 'id', 'marcada' => false])

<input type="hidden" name="{{ $name }}" value="0">
<label for="{{ $id }}" {{ $attributes->class(['ui-check']) }}>
    <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked($marcada)>
    <span>{{ $slot }}</span>
</label>
