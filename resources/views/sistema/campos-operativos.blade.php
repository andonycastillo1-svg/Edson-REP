<x-sistema.pagina :titulo="'Editar campos · '.$nombre.' · '.$registro" :volver="route('sistema.operacion.campos', $modulo)"
                 descripcion="Actualiza solo los campos personalizados que tu cuenta puede editar.">
    <x-sistema.panel>
        <form method="POST" enctype="multipart/form-data" action="{{ route('sistema.operacion.campos.actualizar', [$modulo, $registro]) }}">
            @csrf @method('PUT')
            <x-sistema.campos :modulo="$modulo" :registro="$registro" mostrar-vacio>
                <button class="ui-btn-primary" type="submit">Guardar campos</button>
            </x-sistema.campos>
        </form>
    </x-sistema.panel>
</x-sistema.pagina>
