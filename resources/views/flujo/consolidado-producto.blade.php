<label class="ui-label">{{ $obligatorio ? 'Producto del catálogo' : 'Agregar producto del catálogo' }}
    <select name="producto_codigo" data-searchable="true" data-search-placeholder="Nombre o código" class="ui-input mt-1" @required($obligatorio)>
        <option value="">Selecciona un producto</option>
        @foreach($productos as $producto)
            <option value="{{ $producto->codigo }}" @selected($recuperar && old('producto_codigo') === $producto->codigo)>{{ $producto->nombre }} — {{ $producto->codigo }}</option>
        @endforeach
    </select>
</label>
<label class="ui-label">Cantidad del producto agregado
    <input type="number" min="0" step="1" name="cantidad_nueva" class="ui-input mt-1" value="{{ $recuperar ? old('cantidad_nueva') : '' }}" @required($obligatorio)>
</label>
