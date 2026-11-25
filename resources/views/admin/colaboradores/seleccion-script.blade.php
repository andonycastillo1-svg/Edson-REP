<script>
function colaboradoresPage() {
    return {
        tab: 'activos',
        selectedCodigos: [],
        selectedCount() {
            return this.selectedCodigos.length;
        },
        isSelected(codigo) {
            return this.selectedCodigos.includes(String(codigo));
        },
        toggleSeleccion(codigo) {
            codigo = String(codigo);
            this.selectedCodigos = this.isSelected(codigo)
                ? this.selectedCodigos.filter(item => item !== codigo)
                : [...this.selectedCodigos, codigo];
        },
        seleccionarTodosVisibles(codigos) {
            this.selectedCodigos = [...new Set([...this.selectedCodigos, ...codigos.map(String)])];
        },
        limpiarSeleccion() {
            this.selectedCodigos = [];
        },
        descargarSeleccionados(form) {
            if (this.selectedCodigos.length > 0) form.submit();
        }
    };
}
</script>
