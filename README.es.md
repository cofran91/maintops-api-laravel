# API Laravel de MaintOps

API Laravel para MaintOps, configurada para ejecutarse con Docker Compose directo. No necesitas instalar PHP, Composer, MySQL, Redis ni Mailpit en la maquina host.

La documentacion principal en ingles esta en [README.md](README.md).

## Por Que Docker Directo En Lugar De Laravel Sail

Laravel Sail es util cuando un proyecto acepta depender del runtime publicado en `vendor/laravel/sail`. Este proyecto no usa Sail porque el entorno de desarrollo debe arrancar desde un clon limpio de GitHub usando solo Docker, sin PHP ni Composer locales, y sin depender de archivos generados dentro de `vendor`.

Este repositorio controla su runtime con `Dockerfile` y `compose.yaml`:

- `Dockerfile` instala PHP, las extensiones requeridas y Composer dentro de la imagen de la aplicacion.
- `compose.yaml` levanta la API, MySQL, Redis y Mailpit con nombres de servicio estables.
- `docker/init-development.sh` prepara `.env`, dependencias, `APP_KEY`, migraciones y datos semilla desde el contenedor.

Por esa razon, `laravel/sail` no esta instalado en este repositorio.

## Requisitos

- Docker Desktop o Docker Engine con Docker Compose.
- Git.

## Instalacion Inicial

Clona el repositorio y entra al directorio del proyecto:

```bash
git clone https://github.com/cofran91/maintops-api-laravel.git
cd maintops-api-laravel
```

Construye la imagen de la aplicacion e inicializa el proyecto:

```bash
docker compose build
docker compose run --rm app sh docker/init-development.sh
```

Levanta los servicios:

```bash
docker compose up -d
```

Exporta la especificacion OpenAPI usada por la UI de documentacion:

```bash
docker compose exec app php artisan scramble:export
```

La API queda disponible en:

```text
http://localhost:8000
```

Mailpit queda disponible en:

```text
http://localhost:8025
```

Si el puerto local `3306` ya esta en uso, cambia este valor en `.env`:

```dotenv
FORWARD_DB_PORT=3307
```

La aplicacion dentro de Docker sigue conectandose a MySQL con `DB_HOST=mysql` y `DB_PORT=3306`.

## Uso Diario

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

## Autenticacion Inicial

El script `docker/init-development.sh` ejecuta migraciones y seeders. Crea los roles base del sistema y un usuario `super_admin` para desarrollo:

```text
super_admin
admin
workshop_manager
advisor
technician
```

Credenciales de desarrollo:

```text
email: admin@maint.test
password: password
```

## Documentacion De La API

Scramble genera la especificacion OpenAPI desde rutas, requests, resources y bloques PHPDoc. La UI de documentacion no regenera la especificacion en cada visita. En su lugar, `/docs` sirve el archivo preexportado `public/api.json` y `/docs/api.json` expone ese mismo archivo como JSON.

Regenera la especificacion despues de cambiar rutas API, validaciones, resources o PHPDocs de controladores:

```bash
docker compose exec app php artisan scramble:export
```

## Decisiones De Arquitectura

El codigo mantiene la estructura base de Laravel reconocible, pero agrega limites explicitos donde ayudan a inspeccionar decisiones de ingenieria:

- Las rutas API estan versionadas bajo `routes/api/v1/*`, para que futuros cambios de contrato puedan introducirse sin mezclar versiones en un solo archivo.
- Los workflows de escritura que coordinan persistencia, cambios de estado, registros relacionados o efectos de auditoria viven en `app/Actions/*`. Esto deja visibles los casos de uso de varios pasos y facilita probarlos.
- Las reglas de acceso, las restricciones de valores enviados y el alcance de consultas de listado se mantienen en `app/Policies/*`, `app/Rules/*` y `app/ModelFilters/*`. Asi cada modulo tiene un lugar consistente para autorizacion, invariantes de validacion y comportamiento de filtros a medida que la API crece.
- El soporte transversal o de dominio vive en `app/Support/*` y `app/Services/*`, haciendo explicito el comportamiento reutilizable en lugar de esconderlo dentro de controladores HTTP.
- El vocabulario compartido de dominio vive en `app/Enums/*` cuando los valores string repetidos podrian dispersarse entre seeders, policies, requests, filters, actions y tests.

Estas decisiones son deliberadamente modestas. El proyecto es pequeno, asi que la arquitectura prioriza limites legibles sobre capas extra o abstracciones prematuras.

## Arquitectura De Pruebas

Las pruebas feature se agrupan por area API en `tests/Feature/Api/*`. Esto replica los modulos de produccion y hace que la suite funcione como documentacion ejecutable de cada flujo.

- Las pruebas de autenticacion cubren login, logout, consulta del usuario actual, usuarios inactivos, usuarios eliminados y tokens invalidos.
- Las pruebas de gestion de usuarios estan separadas por comportamiento: crear, listar, ver, actualizar, eliminar y efectos de auditoria.
- Los data providers cubren matrices de roles para ejercer la misma regla sobre `super_admin`, `admin`, `workshop_manager`, `advisor` y `technician` sin duplicar metodos.
- `tests/Feature/Api/Users/Concerns/InteractsWithUsers.php` centraliza fixtures de usuarios, roles y payloads. Cada prueba individual queda enfocada en comportamiento y no en ruido de setup.
- `RefreshDatabase` y `RolesAndAdminUserSeeder` aislan cada prueba mientras ejercitan migraciones, seeders, roles, policies y el flujo real de tokens Sanctum.
- Las pruebas de auditoria verifican efectos de negocio explicitamente, incluyendo snapshots anteriores y nuevos de usuario, no solo codigos de respuesta HTTP.

La suite prioriza cobertura feature porque el comportamiento mas importante de esta API vive en la frontera entre entrada HTTP, autorizacion, estado de base de datos, asignacion de roles y auditoria.

## Librerias Principales

- `laravel/sanctum`: autenticacion API con tokens personales.
- `spatie/laravel-permission`: roles y permisos del sistema.
- `owen-it/laravel-auditing`: soporte de auditoria para cambios en modelos del dominio.
- `spatie/laravel-model-states`: maquinas de estado para flujos operativos.
- `tucker-eric/eloquentfilter`: filtros declarativos para consultas y listados paginados.
- `dedoc/scramble`: documentacion OpenAPI generada desde el codigo Laravel.
- `laravel/telescope`: observabilidad local para requests, queries, jobs, logs y eventos.

## Variables De Entorno

El archivo `.env` no debe subirse al repositorio porque puede contener valores sensibles.

Manten `.env.example` actualizado con valores de ejemplo seguros.

Puertos locales principales y version de la API:

```dotenv
APP_PORT=8000
API_VERSION=1.0.0
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
FORWARD_MAILPIT_SMTP_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

## Pruebas

```bash
docker compose exec app php artisan test
```
