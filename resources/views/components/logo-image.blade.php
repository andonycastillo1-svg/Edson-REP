@props(['class' => ''])

@php
    $logo = file_exists(public_path('img/logo1.png'))
        ? 'img/logo1.png'
        : (file_exists(public_path('img/logo.png')) ? 'img/logo.png' : null);
@endphp

@if($logo)
    <img src="{{ asset($logo) }}" {{ $attributes->merge(['class' => $class, 'alt' => 'Logo']) }}>
@else
    <div {{ $attributes->merge(['class' => trim($class . ' flex items-center justify-center bg-blue-100 text-blue-700 font-bold')]) }}>
        GNS
    </div>
@endif
