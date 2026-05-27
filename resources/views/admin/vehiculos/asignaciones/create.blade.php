@extends('layouts.admin')
@section('title','Nueva asignación vehículo')
@section('content')
<div class="ui-panel p-6">
<h1 class="text-xl font-semibold mb-4">Nueva asignación de vehículo</h1>
@if($errors->any())<div class="text-red-700">{{ implode(' | ', $errors->all()) }}</div>@endif
<form method="POST" action="{{ route('admin.vehiculos.asignaciones.store') }}" class="space-y-3">@csrf
<select name="vehiculo_vin" required><option value="">Vehículo...</option>@foreach($vehiculos as $v)<option value="{{ $v->vin }}">{{ $v->placa }} - {{ $v->vin }}</option>@endforeach</select>
<select name="colaborador_codigo" required><option value="">Colaborador...</option>@foreach($colaboradores as $c)<option value="{{ $c->codigo }}">{{ $c->nombre }}</option>@endforeach</select>
<input type="date" name="fecha_inicio" value="{{ date('Y-m-d') }}" required>
<input type="text" name="estado_inicial_vehiculo" placeholder="Estado inicial" required>
<textarea name="observaciones_asignacion" placeholder="Observaciones"></textarea>
<h3>Productos / repuestos / accesorios</h3>
<div id="items"></div>
<button type="button" onclick="addItem()">+ Producto</button>
<button class="ui-btn-primary">Guardar</button>
</form>
</div>
<script>
const inv = @json($inventarios->map(fn($i)=>['producto_codigo'=>$i->producto_codigo,'bodega_id'=>$i->bodega_id,'label'=>(optional($i->producto)->nombre ?? $i->producto_codigo)+' ('+(optional($i->bodega)->nombre ?? '')+') Stock:'+i.cantidad]));
function addItem(){
 const idx=document.querySelectorAll('.it').length;const d=document.createElement('div');d.className='it';
 d.innerHTML=`<select name="productos[${idx}][producto_codigo]">${inv.map(i=>`<option value="${i.producto_codigo}">${i.label}</option>`).join('')}</select>
 <input name="productos[${idx}][bodega_id]" placeholder="Bodega ID" required>
 <input type="number" min="1" name="productos[${idx}][cantidad]" value="1" required>
 <select name="productos[${idx}][tipo_control]"><option value="cantidad">Cantidad</option><option value="unidad">Unidad</option></select>
 <input name="productos[${idx}][serial]" placeholder="Serial opcional">
 <input name="productos[${idx}][observaciones]" placeholder="Observaciones">`;
 document.getElementById('items').appendChild(d);
}
</script>
@endsection
