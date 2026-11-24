@props(['class' => '', 'configurable' => true])

@php
    $configurado = $configurable ? app(\App\Sistema\Ajustes::class)->general('logo') : null;
    $logo = file_exists(public_path('img/logo1.png'))
        ? 'img/logo1.png'
        : (file_exists(public_path('img/logo.png')) ? 'img/logo.png' : null);
@endphp

@if($configurado)
    <img src="{{ route('sistema.logo') }}" {{ $attributes->merge(['class' => $class, 'alt' => 'Grupo NetSolutions']) }}>
@elseif($logo)
    <img src="{{ asset($logo) }}" {{ $attributes->merge(['class' => $class, 'alt' => 'Grupo NetSolutions']) }}>
@else
    <div {{ $attributes->merge(['class' => trim($class . ' flex items-center justify-center bg-blue-100 text-blue-700 font-bold')]) }}>
        GNS
    </div>
@endif
