@extends('layouts.guest')

@section('title', 'Login - Inventario')

@section('content')
<div class="ui-panel w-full max-w-md overflow-hidden">

    <!-- Header con logo -->
    <div class="border-b border-sky-100 bg-gradient-to-br from-white to-sky-50 px-8 py-8 text-center">
        <div class="mx-auto mb-5 flex h-28 w-48 items-center justify-center rounded-3xl border border-sky-100 bg-white p-4 shadow-sm">
            <x-logo-image class="max-h-20 w-auto" />
        </div>
        <span class="ui-kicker">Sistema corporativo</span>
        <h2 class="mt-2 text-2xl font-extrabold text-slate-950">Iniciar sesión</h2>
        <p class="mt-1 text-sm font-medium text-slate-500">Accede al sistema de inventario</p>
    </div>

    <!-- Form -->
    <div class="px-8 py-8">
        @if (session('status'))
            <div class="ui-alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="ui-alert-error">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="ui-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="ui-input mt-1">
            </div>

            <div>
                <label for="password" class="ui-label">Password</label>
                <input id="password" name="password" type="password" required
                       class="ui-input mt-1">
            </div>
            </div>

            <button type="submit" class="ui-btn-primary w-full">
                Ingresar
            </button>
        </form>
    </div>
</div>  
@endsection
