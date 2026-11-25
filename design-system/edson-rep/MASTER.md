# Sistema de diseño de Edson-REP

La interfaz operativa conserva el logotipo de Grupo NetSolutions, la navegación lateral y la distribución de los módulos. La revisión con UI/UX Pro Max priorizó consistencia en pantallas densas, lectura rápida de estados, foco visible y controles táctiles.

| Uso | Color y comportamiento |
| --- | --- |
| Navegación | Azul marino `#10263F`. El módulo activo se reconoce por el fondo, el borde y `aria-current`. La barra lateral se abre desde el encabezado en pantallas pequeñas. |
| Acción principal | Azul `#1D4F91` con texto blanco. Se reserva para guardar, registrar, crear y confirmar. |
| Interacción | Azul `#2563EB` para el foco visible y fondos azules suaves al pasar el puntero. |
| Superficies | Fondo `#F3F6FA`, paneles blancos `#FFFFFF` y bordes `#D8E1EC`. Los campos usan un borde de mayor contraste. |
| Texto | Inter con alternativa de sistema. Texto principal `#172B4D` y secundario `#52647A`. Se usan cifras tabulares en cantidades e importes. |
| Estados | Verde `#15803D` para éxito, ámbar `#B45309` para pendientes o advertencias, rojo `#B91C1C` para errores o acciones de riesgo. Cada estado tiene una etiqueta escrita. |
| Espaciado | Escala de 4 y 8 px. Los paneles suelen usar entre 16 y 24 px de margen interior. Las acciones y los campos tienen 44 px de altura mínima. |

## Componentes

- `resources/css/app.css` define los tokens `--ui-*`. `tailwind.config.js` adapta los tonos azules y neutros de las vistas existentes a esos tokens.
- `x-ui.encabezado` reúne el título y las acciones de la página. `x-sistema.panel` y `ui-card` organizan los grupos de contenido.
- `x-ui.accion` admite botones y enlaces, con variantes primaria, secundaria y de riesgo. `x-ui.estado` aplica los estilos de etiqueta. `x-ui.icono` comparte la misma familia de iconos.
- `ui-table`, `ui-table-scroll`, `ui-filtros` y `ui-paginacion` organizan listados. En tablas anchas, el desplazamiento horizontal queda dentro de la tabla.
- `ui-input`, `ui-label`, `x-mensajes`, `x-modal` y `data-confirmar` comparten estilos de entrada, respuestas y confirmación. En los diálogos, Escape cierra la ventana y el foco vuelve al botón de apertura.
- `partials.modulos-operativos` suministra los módulos de la barra lateral y de los paneles según las comprobaciones de acceso existentes.
- Las hojas imprimibles conservan su composición para impresión, incluidos los espacios de firma.

Los hallazgos por módulo, las pruebas y las capturas están en la [auditoría de interfaz](../../docs/ESTANDARIZACION_UI_UX.md).
