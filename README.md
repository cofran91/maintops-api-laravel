# MaintOps Laravel API

API Laravel de MaintOps preparada para ejecutarse con Docker directo. No necesitas instalar PHP, Composer, MySQL, Redis ni Mailpit en el host.

## Por que Docker directo y no Laravel Sail

Laravel Sail es util para proyectos que aceptan depender del runtime publicado dentro de `vendor/laravel/sail`. Este proyecto no usa Sail porque el objetivo es que un clone limpio desde GitHub pueda arrancar con solo Docker, sin requerir PHP ni Composer en la maquina local y sin depender de archivos generados dentro de `vendor`.

Este repositorio usa un `Dockerfile` propio y `compose.yaml` para que el flujo inicial sea reproducible con solo Docker:

- `Dockerfile` instala PHP, extensiones necesarias y Composer dentro de la imagen.
- `compose.yaml` levanta la API, MySQL, Redis y Mailpit con nombres de servicio estables.
- `docker/init-development.sh` prepara `.env`, dependencias, `APP_KEY` y migraciones desde el contenedor.

Por esa razon `laravel/sail` no se instala en este repositorio.

## Requisitos

- Docker Desktop o Docker Engine con Docker Compose.
- Git.

## Instalacion inicial

1. Clona el proyecto y entra a la carpeta:

```bash
git clone https://github.com/cofran91/maintops-api-laravel.git
cd maintops-api-laravel
```

2. Prepara el proyecto:

```bash
docker compose build
docker compose run --rm app sh docker/init-development.sh
```

3. Levanta la API:

```bash
docker compose up -d
```

La aplicacion queda disponible en:

```text
http://localhost:8000
```

Endpoints iniciales:

```text
http://localhost:8000/up
http://localhost:8000/api/v1
```

Mailpit queda disponible en:

```text
http://localhost:8025
```

Si el puerto local `3306` ya esta ocupado, cambia en `.env`:

```dotenv
FORWARD_DB_PORT=3307
```

La aplicacion dentro de Docker sigue usando `DB_HOST=mysql` y `DB_PORT=3306`.

## Uso diario

Levantar servicios:

```bash
docker compose up -d
```

Detener servicios:

```bash
docker compose down
```

Ejecutar comandos Artisan:

```bash
docker compose exec app php artisan <comando>
```

Ejecutar Composer:

```bash
docker compose exec app composer <comando>
```

Ver logs:

```bash
docker compose logs -f
```

## Librerias principales

- `laravel/sanctum`: autenticacion API mediante tokens personales.
- `spatie/laravel-permission`: roles y permisos del sistema.
- `owen-it/laravel-auditing`: auditoria de cambios en modelos del dominio.
- `spatie/laravel-model-states`: maquinas de estado para flujos operativos.
- `tucker-eric/eloquentfilter`: filtros declarativos para consultas y listados paginados.
- `dedoc/scramble`: documentacion OpenAPI generada desde rutas, requests y resources.
- `laravel/telescope`: observabilidad local de requests, queries, jobs, logs y eventos.

## Variables de entorno

El archivo `.env` no se debe subir al repositorio porque puede contener datos sensibles.

El archivo `.env.example` debe mantenerse actualizado con valores de ejemplo seguros.

Puertos locales principales:

```dotenv
APP_PORT=8000
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
FORWARD_MAILPIT_SMTP_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

## Pruebas

```bash
docker compose exec app php artisan test
```
