@extends('layouts.app')

@section('content')
    @php($rutaCompras = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user()).'.compras.index')
    <div class="mx-auto max-w-6xl">
        <header class="ui-page-header ui-page-header-body">
            <div>
                <h1 class="ui-page-title">Registrar compra</h1>
                <p class="ui-page-subtitle">Registra una factura por compra. Si llegaste desde Necesidades de compra, revisa el reparto antes de guardar.</p>
            </div>
            <x-internal-navigation :back-url="route($rutaCompras)" :back-route="$rutaCompras" class="mb-0" />
        </header>
        @include('admin.compras._form')
    </div>
@endsection
