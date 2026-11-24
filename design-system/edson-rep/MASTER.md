# Sistema de diseño de Edson-REP

Grupo NetSolutions utiliza una interfaz de trabajo para inventario, operaciones y administración. El punto de partida de UI/UX Pro Max combina diseño plano y vistas de datos densas. Se adapta a una aplicación Laravel de trabajo diario y al logotipo corporativo existente.

| Uso | Estándar |
| --- | --- |
| Navegación | Azul marino `#10243A`, texto claro y módulo activo subrayado visualmente con un borde y fondo. Barra lateral en escritorio y menú desplegable en pantallas pequeñas. |
| Acción principal | Azul `#174F95` sobre blanco. Las demás acciones se apoyan en botones secundarios. |
| Superficies | Fondo `#F3F6FA`, superficie blanca y bordes `#D5DFEB`. Usar una elevación discreta para menús y diálogos. |
| Texto | Inter con fallback de sistema, texto `#182C42` y secundario `#475B72`. El cuerpo usa 16 px y las tablas y controles usan 14 px. Las cifras se alinean con numerales tabulares. |
| Estados | Verde para éxito, ámbar para atención, rojo para error o acción destructiva y azul para información. Cada estado conserva una etiqueta escrita. |
| Espaciado | Escala de 4 y 8 px, paneles de 16 a 24 px y objetivos táctiles de 44 px en las acciones principales. |
| Accesibilidad | Enlace para saltar al contenido, foco visible, títulos ordenados, etiquetas y mensajes asociados, cierre de diálogos con Escape y respeto a la preferencia de movimiento reducido. |

## Componentes

- `layouts.app`, `layouts.admin`, `layouts.operador` y `layouts.guest` consumen la misma base de tipografía, formularios y superficies.
- `x-ui.encabezado` agrupa título, descripción y acciones. `x-panel-operativo` presenta los accesos que ya corresponden a cada ámbito.
- `ui-panel`, `ui-table`, `ui-table-scroll`, `ui-filtros`, `ui-paginacion` y `ui-badge` organizan datos, búsqueda y estados. El desplazamiento horizontal de tablas anchas se limita a su contenedor.
- `ui-input`, `ui-label`, `ui-btn-*`, `x-mensajes`, `x-modal` y `data-confirmar` comparten los estados y la interacción. Los formularios conservan sus métodos, nombres de campo y validaciones.
- Las hojas imprimibles utilizan estilos propios en el documento. Conservan el espacio de firmas y la composición necesaria para su formato.

Las recomendaciones se consultaron con `--design-system` para un panel operativo, `--stack laravel` para Blade y búsquedas de UX específicas sobre foco de diálogos y errores de validación. Se conservó el azul corporativo en lugar de utilizar los acentos genéricos propuestos por la herramienta.
