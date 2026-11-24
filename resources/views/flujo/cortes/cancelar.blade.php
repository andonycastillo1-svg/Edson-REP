<form id="cancelar-corte" method="POST" action="{{ route('flujo.cortes.cancelar', $corte) }}"
    data-confirmar="¿Cancelar el borrador? Su selección quedará en el historial y las inspecciones pendientes podrán usarse en otro corte.">
    @csrf
</form>
