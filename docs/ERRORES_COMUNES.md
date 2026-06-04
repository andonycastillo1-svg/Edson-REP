# Errores comunes

## Falta `vendor/autoload.php`

```bash
composer install
```

## Cambios Blade no se reflejan

```bash
php artisan view:clear
```

## Rutas no actualizan

```bash
php artisan route:clear
php artisan cache:clear
```

## Variables `.env` no actualizan

```bash
php artisan config:clear
php artisan cache:clear
```

## Archivos públicos no abren

Ejecutar:

```bash
php artisan storage:link
```

Verificar que el archivo exista en `storage/app/public` y que `public/storage` apunte correctamente.

## Error 403

Revisar:

- Usuario autenticado.
- `role_id` correcto.
- Ruta dentro del grupo de rol esperado.
- Sesión no expirada por `auto.logout`.

## Métodos inexistentes por `Route::resource`

Detectado como pendiente de confirmar:

- `UsuarioController@show`.
- `ColaboradorController@show` y `destroy`.
- `CompraController@edit`, `update`, `destroy`.

## Nombres largos de índices en MySQL

El proyecto usa varios nombres cortos explícitos (`uq_producto_bodega`, `idx_alerta_colab_producto`, `vpa_vehiculo_estado_idx`, etc.). Si una migración falla por nombre largo, asignar nombre explícito corto.

## Diferencia en `vehiculo_producto_archivos`

La migración y el modelo usan nombres de columnas distintos. **Pendiente de confirmar** antes de usar este modelo para persistir archivos.
