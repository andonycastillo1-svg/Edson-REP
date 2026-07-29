@extends('layouts.guest')

@section('title', 'Login - Inventario')

@section('content')
<div class="login-page">
    <div class="login-page-inner">
        <div class="login-card">

            <div class="login-header">
                <img
                    src="{{ asset('img/logo.png') }}"
                    alt="Grupo Net Solutions"
                    class="login-logo"
                >

                <span class="login-kicker">Sistema corporativo</span>

                <h1 class="login-title">
                    Iniciar sesión
                </h1>

                <p class="login-description">
                    Accede al sistema de inventario
                </p>
            </div>

            <div class="login-body">
                @if (session('status'))
                    <div class="ui-alert-success">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="ui-alert-error">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="ui-label">
                            Correo electrónico
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            class="ui-input mt-1"
                        >
                    </div>

                    <div>
                        <label for="password" class="ui-label">
                            Contraseña
                        </label>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="ui-input mt-1"
                        >
                    </div>

                    <button type="submit" class="login-submit">
                        Ingresar
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection