import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

function normalizeText(value) {
    return (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function enhanceSearchableSelect(select) {
    if (!select || select.dataset.searchEnhanced === '1') return;
    select.dataset.searchEnhanced = '1';

    const input = document.createElement('input');
    input.type = 'search';
    input.placeholder = select.dataset.searchPlaceholder || 'Buscar...';
    const label = select.labels?.[0];
    const name = select.getAttribute('aria-label') || label?.textContent.trim().split('\n')[0].trim();
    input.setAttribute('aria-label', name ? `Buscar opciones de ${name}` : 'Buscar opciones del selector');
    if (select.id) input.setAttribute('aria-controls', select.id);
    input.className = 'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm';
    if (label?.contains(select)) {
        label.before(input);
    } else {
        select.before(input);
    }

    function filterOptions() {
        const query = normalizeText(input.value);
        for (const option of select.options) {
            option.hidden = Boolean(query) && option.value !== '' && !option.selected
                && !normalizeText(option.text).includes(query) && !normalizeText(option.value).includes(query);
        }
    }
    select.addEventListener('options:updated', () => {
        input.value = '';
        filterOptions();
    });
    input.addEventListener('input', filterOptions);
    select.addEventListener('change', filterOptions);
}
window.enhanceSearchableSelect = enhanceSearchableSelect;

function configurarCampo(form) {
    const tipo = form.querySelector('#sistema-tipo-campo');
    const panel = form.querySelector('#sistema-opciones-panel');
    const lista = form.querySelector('#sistema-opciones');
    const defecto = form.querySelector('#sistema-default-lista');
    const ayuda = form.querySelector('#sistema-default-ayuda');
    const limpiar = form.querySelector('[data-limpiar-default]');
    const plantilla = form.querySelector('#sistema-opcion-plantilla');
    let siguienteFila = Math.max(-1, ...Array.from(lista.children, fila => Number(fila.dataset.filaOpcion))) + 1;

    function reflejarOpciones() {
        const seleccion = new Set(Array.from(defecto.selectedOptions, opcion => opcion.value));
        defecto.replaceChildren();
        if (!defecto.multiple) defecto.add(new Option('Sin valor predeterminado', ''));
        for (const fila of lista.children) {
            const etiqueta = fila.querySelector('[data-etiqueta-opcion]').value.trim();
            if (!etiqueta || !fila.querySelector('[type=checkbox]').checked) continue;
            const valor = fila.dataset.filaOpcion;
            defecto.add(new Option(etiqueta, valor, false, seleccion.has(valor)));
        }
    }

    function reflejarTipo() {
        const esLista = ['lista', 'multiple'].includes(tipo.value);
        const esBooleano = ['casilla', 'booleano'].includes(tipo.value);
        for (const [selector, activo] of [
            ['[data-default-texto]', !esLista && !esBooleano && tipo.value !== 'archivo'],
            ['[data-default-booleano]', esBooleano],
        ]) {
            const grupo = form.querySelector(selector);
            grupo.hidden = !activo;
            grupo.querySelector('[name]').disabled = !activo;
        }
        panel.hidden = !esLista;
        defecto.disabled = !esLista;
        const multiple = tipo.value === 'multiple';
        if (defecto.multiple !== multiple) {
            if (defecto.selectedOptions.length > 1 && !multiple) defecto.selectedIndex = -1;
            defecto.multiple = multiple;
        }
        defecto.size = multiple ? 4 : 1;
        limpiar.hidden = !multiple;
        ayuda.textContent = multiple
            ? 'Selecciona una o varias opciones activas. Usa Ctrl, Cmd o Mayús con el teclado para una selección múltiple.'
            : 'Selecciona una opción activa o deja el campo sin valor predeterminado.';
        reflejarOpciones();
    }

    form.querySelector('#sistema-agregar-opcion').addEventListener('click', () => {
        const fila = plantilla.content.cloneNode(true);
        for (const elemento of fila.querySelectorAll('[name], [id], [for], [data-fila-opcion]')) {
            for (const atributo of ['name', 'id', 'for', 'data-fila-opcion']) {
                const valor = elemento.getAttribute(atributo);
                if (valor) elemento.setAttribute(atributo, valor.replace('NUEVA_OPCION', String(siguienteFila)));
            }
        }
        const etiqueta = fila.querySelector('[data-etiqueta-opcion]');
        lista.append(fila);
        etiqueta.focus();
        siguienteFila += 1;
    });
    limpiar.addEventListener('click', () => {
        defecto.selectedIndex = -1;
        defecto.focus();
    });
    lista.addEventListener('input', reflejarOpciones);
    tipo.addEventListener('change', reflejarTipo);
    reflejarTipo();
}

function configurarBuscadorPermisos(panel) {
    const filtro = panel.querySelector('[data-filtro-permisos]');
    if (!filtro) return;
    const grupos = Array.from(panel.querySelectorAll('[data-grupo-permisos]'));
    let abiertos = null;
    filtro.addEventListener('input', () => {
        const consulta = normalizeText(filtro.value);
        if (consulta && abiertos === null) abiertos = grupos.map(grupo => grupo.open);
        let hayResultados = false;
        grupos.forEach((grupo, indice) => {
            const coincidencias = Array.from(grupo.querySelectorAll('[data-regla-permiso]'), regla => {
                regla.hidden = !normalizeText(regla.dataset.busqueda).includes(consulta);
                return !regla.hidden;
            }).some(Boolean);
            grupo.hidden = !coincidencias;
            hayResultados ||= coincidencias;
            if (consulta && coincidencias) grupo.open = true;
            if (!consulta && abiertos) grupo.open = abiertos[indice];
        });
        panel.querySelector('[data-sin-permisos]').hidden = hayResultados;
        if (!consulta) abiertos = null;
    });
}

function conectarValidacion(resumen) {
    const controles = [...document.querySelectorAll('form :is(input, select, textarea):not([type=hidden])')];
    Array.from(resumen.querySelectorAll('[data-campo-error]')).forEach((error, indice) => {
        const [base, ...partes] = error.dataset.campoError.split('.');
        const name = base + partes.map(parte => `[${parte}]`).join('');
        let campo = controles.find(control => control.name === name && !control.disabled);
        if (!campo && partes.length > 0 && /^\d+$/.test(partes.at(-1))) {
            const nombreLista = base + partes.slice(0, -1).map(parte => `[${parte}]`).join('') + '[]';
            campo = controles.filter(control => control.name === nombreLista && !control.disabled)[Number(partes.at(-1))];
        }
        if (!campo) return;
        error.id = `mensaje-validacion-${indice}`;
        const actuales = campo.getAttribute('aria-describedby');
        campo.setAttribute('aria-describedby', [actuales, error.id].filter(Boolean).join(' '));
        campo.setAttribute('aria-invalid', 'true');
        const enlace = document.createElement('a');
        if (!campo.id) campo.id = `campo-validacion-${indice}`;
        enlace.href = `#${campo.id}`;
        enlace.textContent = error.textContent;
        enlace.className = 'underline decoration-rose-400 underline-offset-2';
        enlace.addEventListener('click', event => {
            event.preventDefault();
            for (let grupo = campo.closest('details'); grupo; grupo = grupo.parentElement.closest('details')) grupo.open = true;
            campo.focus();
        });
        error.replaceChildren(enlace);
    });
    resumen.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-searchable="true"]').forEach(enhanceSearchableSelect);
    document.querySelectorAll('[data-formulario-campo]').forEach(configurarCampo);
    document.querySelectorAll('[data-buscador-permisos]').forEach(configurarBuscadorPermisos);
    document.querySelectorAll('[data-resumen-validacion]').forEach(conectarValidacion);
});

document.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const mensaje = event.submitter?.dataset.confirmar || event.target.dataset.confirmar;
    if (mensaje && !window.confirm(mensaje)) event.preventDefault();
});
