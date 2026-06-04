# Comandos útiles

## Instalación

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

## Base de datos

```bash
php artisan migrate
php artisan migrate:status
php artisan db:seed --class=RolesSeeder
php artisan db:seed --class=AdminSeeder
```

## Storage

```bash
php artisan storage:link
```

## Desarrollo

```bash
php artisan serve
npm run dev
```

## Frontend

```bash
npm run dev
npm run build
```

## Cachés

```bash
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan optimize:clear
```

## Diagnóstico

```bash
php artisan route:list
php artisan route:list --name=admin
php artisan route:list --name=operador
php artisan route:list --name=rrhh
php artisan route:list --name=alertas
php artisan about
```

## Pruebas

```bash
php artisan test
```

## Sintaxis PHP

```bash
php -l routes/web.php
php -l app/Http/Controllers/RrhhDashboardController.php
```
