<label class="ui-label">{{ $obligatorio ? 'Producto del catálogo' : 'Agregar producto del catálogo' }}
    <input name="producto_codigo" list="catalogo-productos" class="ui-input mt-1" value="{{ $recuperar ? old('producto_codigo') : '' }}" @required($obligatorio)>
</label>
<label class="ui-label">Cantidad del producto agregado
    <input type="number" min="0" step="1" name="cantidad_nueva" class="ui-input mt-1" value="{{ $recuperar ? old('cantidad_nueva') : '' }}" @required($obligatorio)>
</label>
