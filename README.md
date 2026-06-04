# Sistema Laravel de inventario y gestión de flota

Sistema web desarrollado en Laravel para gestionar inventario, bodegas, compras, traslados, colaboradores, asignaciones de inventario, vehículos/flota, productos o refacciones de vehículos y alertas RRHH por posibles descuentos.

La documentación técnica y funcional fue generada analizando el código real del repositorio.

## Documentación

- [Resumen general](docs/README_GENERAL.md)
- [Instalación y configuración](docs/INSTALACION.md)
- [Roles y permisos](docs/ROLES_Y_PERMISOS.md)
- [Rutas](docs/RUTAS.md)
- [Módulos](docs/MODULOS.md)
- [Base de datos](docs/BASE_DE_DATOS.md)
- [Flujos principales](docs/FLUJOS_PRINCIPALES.md)
- [PDFs y archivos](docs/PDFS_Y_ARCHIVOS.md)
- [Errores comunes](docs/ERRORES_COMUNES.md)
- [Comandos útiles](docs/COMANDOS_UTILES.md)

## Instalación rápida

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

Para desarrollo local:

```bash
php artisan serve
npm run dev
```

## Notas

- El acceso a módulos se controla principalmente por `role_id` y middleware `role`.
- El middleware `auto.logout` cierra sesión por inactividad.
- No guardar contraseñas ni datos sensibles en el repositorio.
