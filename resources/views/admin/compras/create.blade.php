@extends('layouts.app')

@section('content')
    @php($rutaCompras = auth()->user()->rolOperativo() === 2 ? 'operador.compras.index' : 'admin.compras.index')
    <div class="mx-auto max-w-6xl">
        <header class="ui-page-header ui-page-header-body">
            <div>
                <h1 class="ui-page-title">Registrar compra</h1>
                <p class="ui-page-subtitle">Al guardar se registra la compra y se agregan las existencias a la bodega principal.</p>
            </div>
            <x-internal-navigation :back-url="route($rutaCompras)" :back-route="$rutaCompras" class="mb-0" />
        </header>
        @include('admin.compras._form')
    </div>
@endsection
