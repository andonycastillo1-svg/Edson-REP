@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="ui-acceso-form">
        <h1 class="ui-page-title">Iniciar sesión</h1>
        <p class="ui-page-subtitle">Accede al sistema de inventario y operaciones.</p>

        <x-mensajes />

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="email" class="ui-label">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus
                    class="ui-input mt-1" @if($errors->has('email')) aria-invalid="true" aria-describedby="error-correo" @endif>
                <x-input-error id="error-correo" :messages="$errors->get('email')" />
            </div>
            <div>
                <label for="password" class="ui-label">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="ui-input mt-1">
            </div>
            <button type="submit" class="ui-btn-primary w-full">Ingresar</button>
        </form>
    </div>
@endsection
