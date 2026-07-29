@extends('layouts.admin')

@section('title', 'Editar bodega')

@section('content')
<div class="mx-auto w-full max-w-2xl">

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">

        <header class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">
                Editar bodega
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Actualiza la información de {{ $bodega->nombre }}.
            </p>
        </header>

        <form
            method="POST"
            action="{{ route('admin.bodegas.update', $bodega->id) }}"
        >
            @csrf
            @method('PUT')

            <div class="space-y-5 p-6">

                <div>
                    <label for="nombre" class="ui-label">Nombre</label>

                    <input
                        id="nombre"
                        name="nombre"
                        value="{{ old('nombre', $bodega->nombre) }}"
                        required
                        class="ui-input"
                    >

                    @error('nombre')
                        <p class="mt-1 text-sm font-semibold text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label for="ubicacion" class="ui-label">Ubicación</label>

                    <input
                        id="ubicacion"
                        name="ubicacion"
                        value="{{ old('ubicacion', $bodega->ubicacion) }}"
                        class="ui-input"
                    >

                    @error('ubicacion')
                        <p class="mt-1 text-sm font-semibold text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label for="tipo" class="ui-label">Tipo</label>

                    <select id="tipo" name="tipo" required class="ui-input">
                        <option
                            value="Principal"
                            @selected(old('tipo', $bodega->tipo) === 'Principal')
                        >
                            Principal
                        </option>

                        <option
                            value="Regional"
                            @selected(old('tipo', $bodega->tipo) === 'Regional')
                        >
                            Regional
                        </option>
                    </select>

                    @error('tipo')
                        <p class="mt-1 text-sm font-semibold text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

            </div>

            <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                <a
                    href="{{ route('admin.bodegas.index') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-extrabold text-slate-700 hover:bg-slate-100"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-extrabold !text-white hover:!bg-blue-700"
                >
                    Guardar cambios
                </button>
            </footer>
        </form>

    </section>

</div>
@endsection