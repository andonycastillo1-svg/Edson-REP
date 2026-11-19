<script>
    function configurarCondicion(grupo) {
        const motivo = grupo.querySelector('[data-campo="estado_devolucion"]');
        const contenedor = grupo.querySelector('[data-otra]');
        const efecto = contenedor.querySelector('select');
        const detalle = grupo.querySelector('textarea');
        const actualizar = () => {
            const otra = motivo.value === 'otra';
            contenedor.hidden = !otra;
            efecto.disabled = !otra;
            efecto.required = otra;
            detalle.required = otra;
        };
        motivo.addEventListener('change', actualizar);
        actualizar();
    }
    document.querySelectorAll('[data-condicion]').forEach(configurarCondicion);
</script>
