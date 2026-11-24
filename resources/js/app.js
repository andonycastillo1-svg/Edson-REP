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
    const etiquetaDefecto = form.querySelector('[data-default-lista-etiqueta]');
    const multipleDefecto = form.querySelector('[data-default-multiple]');
    const opcionesDefecto = form.querySelector('[data-default-opciones]');
    const ayuda = form.querySelector('#sistema-default-ayuda');
    const limpiar = form.querySelector('[data-limpiar-default]');
    const plantilla = form.querySelector('#sistema-opcion-plantilla');
    const agregar = form.querySelector('#sistema-agregar-opcion');
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
        opcionesDefecto.replaceChildren();
        if (defecto.multiple) {
            for (const opcion of defecto.options) {
                const label = document.createElement('label');
                label.className = 'ui-check';
                const casilla = document.createElement('input');
                casilla.type = 'checkbox';
                casilla.value = opcion.value;
                casilla.checked = opcion.selected;
                label.append(casilla, opcion.label);
                opcionesDefecto.append(label);
            }
            if (!opcionesDefecto.children.length) {
                const ayuda = document.createElement('p');
                ayuda.className = 'p-2 text-sm';
                ayuda.textContent = 'Agrega una opción activa para elegirla aquí.';
                opcionesDefecto.append(ayuda);
            }
        }
        agregar.disabled = lista.children.length >= 100;
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
        defecto.hidden = multiple;
        etiquetaDefecto.hidden = multiple;
        multipleDefecto.hidden = !multiple;
        limpiar.hidden = !multiple;
        ayuda.textContent = multiple
            ? 'Marca las opciones que aparecerán seleccionadas en el formulario.'
            : 'Selecciona una opción activa o deja el campo sin valor predeterminado.';
        reflejarOpciones();
    }

    agregar.addEventListener('click', () => {
        const fila = plantilla.content.cloneNode(true);
        for (const elemento of fila.querySelectorAll('[name], [id], [for], [data-fila-opcion]')) {
            for (const atributo of ['name', 'id', 'for', 'data-fila-opcion']) {
                const valor = elemento.getAttribute(atributo);
                if (valor) elemento.setAttribute(atributo, valor.replace('NUEVA_OPCION', String(siguienteFila)));
            }
        }
        const etiqueta = fila.querySelector('[data-etiqueta-opcion]');
        lista.append(fila);
        agregar.disabled = lista.children.length >= 100;
        etiqueta.focus();
        siguienteFila += 1;
    });
    limpiar.addEventListener('click', () => {
        defecto.selectedIndex = -1;
        for (const casilla of opcionesDefecto.querySelectorAll('[type=checkbox]')) casilla.checked = false;
    });
    opcionesDefecto.addEventListener('change', event => {
        const opcion = [...defecto.options].find(opcion => opcion.value === event.target.value);
        if (opcion) opcion.selected = event.target.checked;
    });
    lista.addEventListener('click', event => {
        const quitar = event.target.closest('[data-quitar-opcion]');
        if (!quitar) return;
        quitar.closest('[data-fila-opcion]').remove();
        reflejarOpciones();
        agregar.focus();
    });
    lista.addEventListener('input', reflejarOpciones);
    const modulo = form.querySelector('[data-modulo-campo]');
    const selectoresPermisos = [...form.querySelectorAll('[data-permisos-campo]')];
    function priorizarPermisos() {
        for (const selector of selectoresPermisos) {
            const seleccionado = selector.value;
            const grupos = [...selector.querySelectorAll('optgroup')];
            grupos.sort((a, b) => {
                if (a.dataset.modulo === modulo.value) return -1;
                if (b.dataset.modulo === modulo.value) return 1;
                return a.label.localeCompare(b.label, 'es');
            });
            selector.append(...grupos);
            selector.value = seleccionado;
        }
    }
    modulo.addEventListener('change', priorizarPermisos);
    priorizarPermisos();
    tipo.addEventListener('change', reflejarTipo);
    reflejarTipo();
}

function configurarBuscadorPermisos(panel) {
    const filtro = panel.querySelector('[data-filtro-permisos]');
    if (!filtro) return;
    const estado = panel.querySelector('[data-filtro-estado]');
    const expandir = panel.querySelector('[data-expandir-permisos]');
    const resumen = panel.querySelector('[data-permisos-resumen]');
    const grupos = [...panel.querySelectorAll('[data-grupo-permisos]')];
    const reglas = [...panel.querySelectorAll('[data-regla-permiso]')];
    const texto = new Map(reglas.map(regla => [regla, normalizeText(regla.dataset.busqueda)]));
    let abiertos = null;

    function actualizarBoton() {
        const visibles = grupos.filter(grupo => !grupo.hidden);
        expandir.hidden = visibles.length === 0;
        expandir.textContent = visibles.every(grupo => grupo.open) ? 'Contraer módulos' : 'Expandir módulos';
    }

    function actualizarResumen() {
        let pendientes = 0;
        for (const regla of reglas) {
            const cambio = regla.querySelector('input:checked')?.value !== regla.dataset.reglaGuardada;
            regla.querySelector('[data-regla-modificada]').hidden = !cambio;
            if (cambio) pendientes += 1;
        }
        const visibles = reglas.filter(regla => !regla.hidden).length;
        resumen.textContent = `${visibles} de ${reglas.length} permisos · ${pendientes === 1 ? '1 cambio sin guardar' : pendientes + ' cambios sin guardar'}`;
    }

    function aplicarFiltros() {
        const palabras = normalizeText(filtro.value).split(/\s+/).filter(Boolean);
        const filtrando = palabras.length > 0 || estado.value !== '';
        if (filtrando && abiertos === null) abiertos = grupos.map(grupo => grupo.open);
        for (const regla of reglas) {
            regla.hidden = !palabras.every(palabra => texto.get(regla).includes(palabra))
                || Boolean(estado.value && estado.value !== regla.dataset.efectivo);
        }
        grupos.forEach((grupo, indice) => {
            for (const categoria of grupo.querySelectorAll('[data-categoria-permisos]')) {
                categoria.hidden = !categoria.querySelector('[data-regla-permiso]:not([hidden])');
            }
            grupo.hidden = !grupo.querySelector('[data-categoria-permisos]:not([hidden])');
            if (filtrando && !grupo.hidden) grupo.open = true;
            if (!filtrando && abiertos) grupo.open = abiertos[indice];
        });
        panel.querySelector('[data-sin-permisos]').hidden = grupos.some(grupo => !grupo.hidden);
        if (!filtrando) abiertos = null;
        actualizarBoton();
        actualizarResumen();
    }

    expandir.addEventListener('click', () => {
        const visibles = grupos.filter(grupo => !grupo.hidden);
        const abrir = visibles.some(grupo => !grupo.open);
        for (const grupo of visibles) grupo.open = abrir;
        actualizarBoton();
    });
    filtro.addEventListener('input', aplicarFiltros);
    estado.addEventListener('change', aplicarFiltros);
    panel.addEventListener('toggle', actualizarBoton, true);
    panel.addEventListener('change', event => {
        if (event.target.type === 'radio') actualizarResumen();
    });
    aplicarFiltros();
}

function conectarValidacion(resumen) {
    const origen = resumen.dataset.validacionOrigen;
    const formulario = origen ? `form[data-validacion-formulario="${CSS.escape(origen)}"]` : 'form';
    const controles = [...document.querySelectorAll(`${formulario} :is(input, select, textarea):not([type=hidden]):not(:disabled)`)];
    Array.from(resumen.querySelectorAll('[data-campo-error]')).forEach((error, indice) => {
        const [base, ...partes] = error.dataset.campoError.split('.');
        const name = base + partes.map(parte => `[${parte}]`).join('');
        const alternativas = controles.filter(control => control.name === name
            || (base === 'reglas' && control.name === `reglas[${partes.join('.')}]`));
        let campo = alternativas.find(control => control.type !== 'radio' || control.checked) ?? alternativas[0];
        if (!campo && partes.length > 0 && /^\d+$/.test(partes.at(-1))) {
            const nombreLista = base + partes.slice(0, -1).map(parte => `[${parte}]`).join('') + '[]';
            campo = controles.filter(control => control.name === nombreLista)[Number(partes.at(-1))];
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
        enlace.className = 'ui-error-link';
        enlace.addEventListener('click', event => {
            event.preventDefault();
            campo.closest('[data-panel-pestana]')?.dispatchEvent(new Event('mostrar:pestana', { bubbles: true }));
            for (let grupo = campo.closest('details'); grupo; grupo = grupo.parentElement.closest('details')) grupo.open = true;
            const dialogo = campo.closest('[data-ui-dialogo][id]');
            if (dialogo && !campo.getClientRects().length) {
                document.querySelector('[aria-controls="' + CSS.escape(dialogo.id) + '"][aria-haspopup="dialog"]')?.click();
                requestAnimationFrame(() => campo.focus());
                return;
            }
            campo.focus();
        });
        error.replaceChildren(enlace);
    });
    resumen.focus();
}

function conectarDatosCuenta(form) {
    const rol = form.querySelector('#rol-cuenta');
    function reflejarAmbito() {
        const ambito = rol.selectedOptions[0]?.dataset.ambito;
        for (const grupo of form.querySelectorAll('[data-para-ambito]')) {
            grupo.hidden = grupo.dataset.paraAmbito !== ambito;
            const control = grupo.querySelector('select');
            control.disabled = grupo.hidden;
            control.required = !grupo.hidden;
        }
    }
    rol.addEventListener('change', reflejarAmbito);
    reflejarAmbito();
}

function conectarOrden(lista) {
    const aviso = lista.querySelector('[data-orden-aviso]');
    const obtenerFilas = () => [...lista.querySelectorAll('[data-fila-orden]')];
    function actualizarBotones() {
        const filas = obtenerFilas();
        filas.forEach((fila, indice) => {
            fila.querySelector('[data-mover="arriba"]').disabled = indice === 0;
            fila.querySelector('[data-mover="abajo"]').disabled = indice === filas.length - 1;
        });
    }
    for (const boton of lista.querySelectorAll('[data-mover]')) boton.hidden = false;
    const filas = obtenerFilas().sort((a, b) => a.querySelector('[data-posicion]').valueAsNumber - b.querySelector('[data-posicion]').valueAsNumber);
    for (const fila of filas) lista.insertBefore(fila, aviso);
    actualizarBotones();
    lista.addEventListener('click', event => {
        const boton = event.target.closest('[data-mover]');
        if (!boton) return;
        const fila = boton.closest('[data-fila-orden]');
        const filas = obtenerFilas();
        const desde = filas.indexOf(fila);
        const hacia = desde + (boton.dataset.mover === 'arriba' ? -1 : 1);
        if (!filas[hacia]) return;
        if (hacia < desde) filas[hacia].before(fila);
        else filas[hacia].after(fila);
        obtenerFilas().forEach((elemento, indice) => elemento.querySelector('[data-posicion]').value = indice);
        actualizarBotones();
        (boton.disabled ? fila.querySelector('[data-posicion]') : boton).focus();
        aviso.textContent = `${fila.querySelector('legend').textContent.trim()}, posición ${hacia + 1}.`;
    });
}

function conectarVisibilidad(form) {
    const orden = form.querySelector('#orden-datos');
    function actualizarOpciones() {
        for (const opcion of orden.options) {
            const casilla = form.querySelector(`#columna-visible-${opcion.value}`);
            opcion.disabled = Boolean(casilla && !casilla.checked);
        }
        if (orden.selectedOptions[0]?.disabled) {
            orden.selectedIndex = [...orden.options].findIndex(opcion => !opcion.disabled);
        }
    }
    form.addEventListener('change', event => {
        if (event.target.id.startsWith('columna-visible-')) actualizarOpciones();
    });
    actualizarOpciones();
}

function conectarPestanas(grupo) {
    const enlaces = [...grupo.querySelectorAll('[data-pestana]')];
    const paneles = [...grupo.querySelectorAll('[data-panel-pestana]')];
    function mostrar(fragmento) {
        const destino = fragmento && document.getElementById(fragmento.slice(1))?.closest('[data-panel-pestana]');
        const elegido = paneles.includes(destino) ? destino : paneles[0];
        paneles.forEach(panel => panel.hidden = panel !== elegido);
        enlaces.forEach(enlace => {
            if (enlace.hash === `#${elegido.id}`) enlace.setAttribute('aria-current', 'location');
            else enlace.removeAttribute('aria-current');
        });
    }
    grupo.addEventListener('click', event => {
        const enlace = event.target.closest('a[href^="#"]');
        if (enlace && paneles.some(panel => `#${panel.id}` === enlace.hash)) mostrar(enlace.hash);
    });
    grupo.addEventListener('mostrar:pestana', event => {
        const hash = `#${event.target.id}`;
        history.replaceState(null, '', hash);
        mostrar(hash);
    });
    window.addEventListener('hashchange', () => mostrar(location.hash));
    grupo.addEventListener('invalid', event => {
        const panel = event.target.closest('[data-panel-pestana]');
        if (panel?.hidden) panel.dispatchEvent(new Event('mostrar:pestana', { bubbles: true }));
    }, true);
    mostrar(location.hash || grupo.dataset.pestanaInicial);
}

function conectarDestinatarios(form) {
    let pendiente = false;
    form.addEventListener('change', () => {
        pendiente = [...form.querySelectorAll('[type=checkbox]')].some(casilla => casilla.checked !== casilla.defaultChecked);
    });
    document.querySelector('[data-buscar-destinatarios]').addEventListener('submit', event => {
        if (pendiente && !window.confirm('Hay cambios pendientes en esta regla. ¿Continuar sin guardarlos?')) event.preventDefault();
    });
}

function conectarMenuModulos(boton) {
    const menu = document.getElementById(boton.getAttribute('aria-controls'));
    const escritorio = window.matchMedia('(min-width: 1024px)');

    function cerrar(devolverFoco = false) {
        menu.removeAttribute('data-abierto');
        boton.setAttribute('aria-expanded', 'false');
        if (devolverFoco) boton.focus();
    }

    boton.addEventListener('click', () => {
        if (menu.hasAttribute('data-abierto')) {
            cerrar(true);
            return;
        }

        menu.setAttribute('data-abierto', '');
        boton.setAttribute('aria-expanded', 'true');
        const enlace = menu.querySelector('[aria-current="page"], a');
        enlace?.focus();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.hasAttribute('data-abierto')) cerrar(true);
    });
    document.addEventListener('pointerdown', event => {
        if (!boton.contains(event.target) && !menu.contains(event.target)) cerrar();
    });
    document.addEventListener('focusin', event => {
        if (!boton.contains(event.target) && !menu.contains(event.target)) cerrar();
    });
    escritorio.addEventListener('change', () => cerrar());
}

function conectarDialogo(dialogo) {
    let abierto = false;
    let origen;

    const enfocados = () => [...dialogo.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex]')]
        .filter(elemento => !elemento.disabled && elemento.tabIndex >= 0 && elemento.getClientRects().length > 0);

    function comprobarVisibilidad() {
        const visible = dialogo.getClientRects().length > 0;
        if (visible === abierto) return;

        abierto = visible;
        if (abierto) {
            origen = document.activeElement;
            const inicial = dialogo.querySelector('[data-foco-dialogo]') ?? enfocados()[0];
            inicial?.focus();
        } else if (origen?.isConnected && (document.activeElement === document.body || dialogo.contains(document.activeElement))) {
            origen.focus();
        }
    }

    const observador = new MutationObserver(comprobarVisibilidad);
    observador.observe(dialogo, { attributes: true, attributeFilter: ['class', 'style', 'hidden', 'x-cloak'] });
    dialogo.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;

        const elementos = enfocados();
        const destino = event.shiftKey ? elementos.at(-1) : elementos[0];
        const limite = event.shiftKey ? elementos[0] : elementos.at(-1);
        if (destino && document.activeElement === limite) {
            event.preventDefault();
            destino.focus();
        }
    });
    comprobarVisibilidad();
}

function conectarBusquedaColaborador(formulario) {
    const campo = formulario.elements.busqueda_colaborador;
    const destino = formulario.elements.colaborador_codigo;
    const estado = formulario.querySelector('[data-colaborador-estado]');
    let consulta;
    let espera;
    function programar() {
        clearTimeout(espera);
        consulta?.abort();
        const anterior = destino.value || destino.dataset.colaboradorAnterior;
        const texto = campo.value.trim();
        destino.replaceChildren(new Option('Selecciona un colaborador', ''));
        estado.textContent = texto.length < 2 ? 'Escribe al menos dos caracteres.' : 'Buscando colaboradores…';
        if (texto.length < 2) return;
        espera = setTimeout(async () => {
            consulta = new AbortController();
            const url = new URL(formulario.dataset.buscarColaborador, window.location.href);
            url.searchParams.set('q', texto);
            try {
                const respuesta = await fetch(url, { signal: consulta.signal, headers: { Accept: 'application/json' } });
                if (!respuesta.ok) throw new Error();
                const personas = await respuesta.json();
                personas.forEach(persona => destino.add(new Option(`${persona.nombre} · ${persona.codigo} · ${persona.region}`, persona.codigo)));
                if (personas.some(persona => persona.codigo === anterior)) destino.value = anterior;
                estado.textContent = personas.length ? `${personas.length} coincidencia(s).` : 'Sin coincidencias en tus regiones.';
            } catch (error) {
                if (error.name !== 'AbortError') estado.textContent = 'No se pudo consultar el directorio. Intenta de nuevo.';
            }
        }, 250);
    }
    campo.addEventListener('input', programar);
    if (campo.value) programar();
}

function conectarFiltrosExcel(enlace) {
    const campos = Object.entries(JSON.parse(enlace.dataset.excelCampos));
    const actualizar = () => {
        const url = new URL(enlace.href);
        campos.forEach(([parametro, id]) => {
            const valor = document.getElementById(id)?.value.trim();
            if (valor) url.searchParams.set(parametro, valor);
            else url.searchParams.delete(parametro);
        });
        enlace.href = url;
    };
    campos.forEach(([, id]) => {
        document.getElementById(id)?.addEventListener('input', actualizar);
    });
    actualizar();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-buscar-colaborador]').forEach(conectarBusquedaColaborador);
    document.querySelectorAll('[data-excel-campos]').forEach(conectarFiltrosExcel);
    document.querySelectorAll('select[data-searchable="true"]').forEach(enhanceSearchableSelect);
    document.querySelectorAll('[data-formulario-campo]').forEach(configurarCampo);
    document.querySelectorAll('[data-buscador-permisos]').forEach(configurarBuscadorPermisos);
    document.querySelectorAll('[data-lista-orden]').forEach(conectarOrden);
    document.querySelectorAll('[data-presentacion]').forEach(conectarVisibilidad);
    document.querySelectorAll('[data-pestanas]').forEach(conectarPestanas);
    document.querySelectorAll('[data-datos-cuenta]').forEach(conectarDatosCuenta);
    document.querySelectorAll('[data-destinatarios]').forEach(conectarDestinatarios);
    document.querySelectorAll('[data-resumen-validacion]').forEach(conectarValidacion);
    document.querySelectorAll('[data-abrir-modulos]').forEach(conectarMenuModulos);
    document.querySelectorAll('[data-ui-dialogo]').forEach(conectarDialogo);
});

document.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const mensaje = event.submitter?.dataset.confirmar || event.target.dataset.confirmar;
    if (mensaje && !window.confirm(mensaje)) event.preventDefault();
});
