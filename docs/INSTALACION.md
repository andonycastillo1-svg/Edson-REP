# Instalación

## Requisitos

- PHP compatible con la versión de Laravel del proyecto (**Pendiente de confirmar versión exacta en el entorno de despliegue**).
- Composer.
- Node.js y npm.
- MySQL/MariaDB o motor compatible con las migraciones.
- Extensiones PHP habituales para Laravel: PDO, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo.

## Configuración inicial

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
```

## Variables `.env` mínimas

```env
APP_NAME="Sistema Inventario"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario
DB_PASSWORD=contraseña

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
AUTO_LOGOUT_MINUTES=120
```

No guardar contraseñas reales en documentación ni en el repositorio.

## Migraciones y seeders

```bash
php artisan migrate
```

Seeders detectados:

```bash
php artisan db:seed --class=RolesSeeder
php artisan db:seed --class=AdminSeeder
```

`DatabaseSeeder` no llama explícitamente a `RolesSeeder` ni `AdminSeeder`; **Pendiente de confirmar** si se usan manualmente en producción.

## Storage público

El sistema guarda PDFs, imágenes y adjuntos en disco `public` en varios flujos. Ejecutar:

```bash
php artisan storage:link
```

## Frontend

Producción:

```bash
npm run build
```

Desarrollo local:

```bash
npm run dev
```

## Levantar localmente

```bash
php artisan serve
npm run dev
```

## Limpieza de caché

```bash
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
```
