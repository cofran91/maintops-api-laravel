# API Laravel De MaintOps

Documentación en inglés: [README.md](README.md).

MaintOps Laravel API es el backend transaccional de una plataforma de operaciones de mantenimiento vehicular. Contiene autenticación, autorización, flujos de negocio, transiciones de estado, automatización de scheduling, auditoría, eventos operativos, contratos internos de integración y envío de correos.

El proyecto está diseñado como parte del stack completo de portafolio MaintOps. Puede ejecutarse solo para revisar el backend, pero la experiencia completa usa:

- `maintops-web-vue` para la consola de navegador.
- `maintops-realtime-node` para actualizaciones operativas en vivo.
- `maintops-analytics-fastapi` para analítica read-only.
- `maintops-stack` para el ambiente local replicable que ejecuta todos los servicios juntos.

## Qué Demuestra Este Proyecto

- Diseño de API Laravel con rutas versionadas, request validation, resources, policies y feature tests.
- Modelado de dominio para owners, vehicles, workshops, technicians, vehicle systems, tasks, plans, maintenance orders y order items.
- Control de acceso por roles con alcances operativos para administradores, advisors, workshop managers y technicians.
- State machines para maintenance orders, order items y vehicle-specific tasks.
- Flujos automatizados que generan trabajos recomendados y agendan trabajo aprobado.
- Patrón transactional outbox para eventos operativos entre servicios.
- Integración con Redis Streams sin debilitar la transacción de base de datos.
- Emisión de service tokens para Realtime y Analytics sin exponer detalles internos de Sanctum.
- Auditoría, documentación OpenAPI generada, observabilidad interna, correo encolado y desarrollo solo con Docker.

## Stack Técnico

| Herramienta | Propósito |
| --- | --- |
| Laravel 13 | API HTTP, console commands, queue workers, scheduler, policies, resources y tests. |
| PHP 8.3 | Runtime usado por la imagen Docker y comandos de validación. |
| Laravel Sanctum | Autenticación API para navegador y clientes API. |
| Spatie Laravel Permission | Gestión de roles y permisos. |
| Spatie Laravel Model States | State machines explícitas para ciclos de vida operativos. |
| OwenIt Laravel Auditing | Auditoría de cambios de modelos y workflows. |
| EloquentFilter | Filtrado declarativo para endpoints paginados. |
| Dedoc Scramble | OpenAPI generado desde rutas, requests, resources y PHPDoc. |
| Laravel Telescope | Observabilidad local para requests, queries, jobs, logs, eventos y mail. |
| MySQL | Fuente de verdad transaccional. |
| Redis Streams | Transporte de eventos operativos entre servicios. |
| Mailpit | Sandbox local de correos para recuperación de contraseña y correos operativos al owner. |

## Rol Dentro Del Ecosistema MaintOps

Laravel es la fuente de verdad para identidad, autorización, datos transaccionales y decisiones de negocio.

- La consola Vue autentica contra Laravel y consume la API versionada.
- El gateway Realtime recibe service tokens de corta duración emitidos por Laravel y consume eventos Laravel desde Redis Streams.
- La API Analytics recibe service tokens de corta duración emitidos por Laravel, importa snapshots internos mediante service key y mantiene su modelo de lectura actualizado desde el mismo Redis Stream.
- Mailpit captura correos localmente para revisar recuperación de contraseña y notificaciones operativas sin credenciales SMTP externas.

Los servicios externos no se conectan a MySQL de Laravel y no reutilizan tokens Sanctum directamente. Validan service tokens o service keys creados específicamente para su límite de integración.

## Estructura Del Proyecto

El código mantiene una forma Laravel reconocible y agrega límites explícitos donde el dominio lo necesita:

```text
app/
  Actions/          Workflows de escritura de varios pasos y actualizaciones de agregados.
  Console/Commands/ Automatización operativa programada.
  Enums/            Vocabulario de dominio para roles, estados y códigos.
  Http/Controllers/ Controladores API versionados y controladores web internos.
  Http/Requests/    Validación de requests y restricciones de valores enviados.
  Http/Resources/   Serialización de respuestas API.
  Jobs/             Publicación de eventos y entrega de correos en cola.
  Mail/             Correos operativos dirigidos al owner.
  ModelFilters/     Filtros de consulta para listados.
  Models/           Modelos Eloquent transaccionales.
  Notifications/    Entrega de notificación de recuperación de contraseña.
  Policies/         Reglas de autorización por recurso.
  Rules/            Reglas reutilizables de validación de dominio.
  Services/         Servicios transversales e integraciones.
  States/           State machines de órdenes, items y tareas de vehículo.
  Support/          Helpers compartidos como auditoría explícita.
routes/
  api.php           Prefijo de versión y carga de módulos de rutas.
  api/v1/           Rutas API versionadas por área de dominio.
  console.php       Definiciones del scheduler.
database/
  migrations/       Esquema transaccional.
  seeders/          Roles, datos base y datos demo de portafolio.
tests/
  Feature/          Tests de API, consola, eventos operativos y herramientas internas.
```

Los controladores se mantienen delgados. Las decisiones que coordinan persistencia, estados, auditoría o eventos viven en actions, states, services, commands y jobs para que sean fáciles de revisar y probar.

## Modelo De Dominio Y Roles

MaintOps modela la operación de servicio de una empresa de mantenimiento vehicular:

- Owners representan clientes.
- Vehicles pertenecen a owners y guardan datos operativos como placa y odómetro.
- Vehicle systems describen capacidades de taller como engine, brakes, electrical, cooling y tires.
- Workshops tienen managers, technicians, sistemas soportados, ciudad, dirección y horarios semanales.
- Maintenance tasks pueden ser tareas reutilizables de catálogo o problemas específicos reportados por el cliente para un vehículo.
- Maintenance plans agrupan tareas reutilizables y definen intervalos recomendados por días y/o kilómetros.
- Maintenance orders agrupan trabajo solicitado para un vehículo.
- Maintenance order items representan actividades individuales que pueden aprobarse, agendarse, iniciarse, completarse, rechazarse o cancelarse.

Roles principales:

- `super_admin` y `admin`: gestionan catálogos, usuarios, talleres, owners, vehicles, plans, tasks, orders y transiciones excepcionales.
- `advisor`: crea registros operativos de cara al cliente y maneja transiciones orientadas a aprobación.
- `workshop_manager`: opera dentro del alcance de su taller asignado.
- `technician`: ve trabajo asignado y actualiza estados ejecutables de items.

Los permisos exactos se aplican con policies, request rules y tests.

## Flujo Operativo

1. Preparar el catálogo con vehicle systems, workshops, users, reusable tasks y maintenance plans.
2. Registrar owners y vehicles.
3. Crear una maintenance order para un vehículo, opcionalmente con tareas específicas reportadas por el cliente.
4. Ejecutar `maintenance-orders:generate-items` para agregar tareas recomendadas por planes vencidos y tareas activas del vehículo.
5. Capturar aprobación del owner. Los items aceptados quedan disponibles para scheduling; los rechazados quedan como historial de decisión.
6. Ejecutar `maintenance-orders:schedule-approved` para asignar workshop, technician y horarios según capacidades, horario laboral, disponibilidad y duración.
7. Los technicians inician y completan items agendados. Las órdenes y tareas relacionadas cambian por state machines.
8. Las órdenes completadas pueden entregarse. Trabajo agendado o en progreso puede cancelarse según reglas de estado.
9. Dashboard y audit endpoints exponen visibilidad operacional con alcance por rol.

Dos comandos de dominio corren cada dos minutos:

```text
maintenance-orders:generate-items
maintenance-orders:schedule-approved
```

La recuperación de eventos operativos corre cada minuto:

```text
operational-events:dispatch --limit=100
```

## Eventos Operativos

MaintOps registra cambios relevantes del ciclo de vida de órdenes e items en un outbox transaccional antes de publicarlos en Redis Streams. La fila de evento se crea en MySQL dentro de la misma transacción del cambio de negocio, y el job de publicación se encola solo después del commit.

Redis Streams se usa en lugar de Redis Pub/Sub porque estos eventos son datos de integración, no mensajes live descartables. Streams mantiene un log ordenado que Realtime y Analytics pueden consumir con consumer groups independientes, recuperar después de caídas y procesar a su propio ritmo.

El stream se configura con `OPERATIONS_EVENT_STREAM`. En el stack completo, todos los servicios usan:

```text
maintops:events
```

Si Redis no está disponible, el evento queda sin publicar en MySQL y puede reintentarse por el worker o por el comando programado de recuperación.

## Integración Con Analytics

Analytics no lee directamente la base MySQL de Laravel. Laravel expone un endpoint interno protegido por service key:

```text
GET /api/v1/internal/analytics/initial-sync/{resource}
```

El snapshot es cursor-paginated e incluye datos de proyección para workshops, technicians, maintenance tasks, maintenance orders y maintenance order items. Datos de contacto, documentos, credenciales y otra PII de owners/users se excluyen intencionalmente.

La service key compartida se configura con `OPERATIONS_ANALYTICS_SERVICE_KEY`.

## Service Tokens Externos

Usuarios autenticados pueden solicitar service tokens de corta duración para servicios externos:

```text
POST /api/v1/auth/service-token
```

Audiences soportadas:

- `realtime`: usada por el gateway Socket.IO en Node.
- `analytics`: usada por la API FastAPI Analytics y restringida a roles administrativos.

El token contiene solo user id, roles, workshop scope, audience, issued-at, expiration y token id único. Realtime y Analytics validan el token con `SERVICE_TOKEN_SECRET` y no necesitan acceso a MySQL ni detalles internos de Sanctum.

## Correos Y Recuperación De Contraseña

Laravel encola dos flujos de correo:

- Correos de recuperación de contraseña generados por endpoints públicos de auth.
- Correos operativos al owner cuando una maintenance order se agenda o se completa.

Los correos operativos se envían solo al owner del vehículo. Advisors, technicians y workshop managers reciben actualizaciones operativas dentro de la plataforma mediante notificaciones realtime.

Ambientes locales y demo usan Mailpit por defecto. Mailpit captura correos salientes y los expone en una bandeja web sin enviarlos a internet:

```text
http://localhost:8025
```

Usa solo datos demo. Mailpit es visible para cualquiera con acceso a la URL local/demo.

## Documentación API Y Observabilidad

Scramble genera la especificación OpenAPI desde rutas, requests, resources y PHPDoc. La UI sirve el archivo exportado `public/api.json`.

Herramientas internas protegidas:

- `/admin/login`: login web para herramientas internas.
- `/docs`: UI de documentación Scramble.
- `/docs/api.json`: OpenAPI JSON exportado.
- `/telescope`: dashboard de observabilidad local.

Estas herramientas requieren sesión web normal y un usuario activo con rol `super_admin`. La autenticación API sigue basada en tokens con Sanctum.

Regenera OpenAPI después de cambiar rutas, requests, resources o PHPDocs de controladores:

```bash
docker compose exec app php artisan scramble:export
```

## Ejecutar Standalone Con Docker

La forma recomendada de revisar el producto completo es `maintops-stack`. Usa el Compose standalone cuando quieras enfocarte solo en la API Laravel.

Requisitos:

- Docker Engine o Docker Desktop con Docker Compose.
- Git.

Clonar e inicializar:

```bash
git clone https://github.com/cofran91/maintops-api-laravel.git
cd maintops-api-laravel
docker compose build
docker compose run --rm app sh docker/init-development.sh
docker compose up -d
```

La API queda disponible en:

```text
http://localhost:8000
```

Mailpit queda disponible en:

```text
http://localhost:8025
```

Si el puerto local `3306` ya está en uso, cambia este valor en `.env`:

```dotenv
FORWARD_DB_PORT=3307
```

Dentro de Docker la aplicación sigue conectándose a MySQL con `DB_HOST=mysql` y `DB_PORT=3306`.

## Comandos Útiles

```bash
docker compose up -d
docker compose down
docker compose logs -f
docker compose exec app php artisan <command>
docker compose exec app composer <command>
```

Ejecutar automatización de dominio manualmente:

```bash
docker compose exec app php artisan maintenance-orders:generate-items
docker compose exec app php artisan maintenance-orders:schedule-approved
docker compose exec app php artisan operational-events:dispatch --limit=100
```

Revisar workers en el ambiente standalone:

```bash
docker compose logs -f queue queue-events queue-mail scheduler
```

## Datos Demo

`docker/init-development.sh` ejecuta migraciones y seeders. Crea roles, un usuario `super_admin` de desarrollo y un dataset demo de portafolio.

Todos los usuarios sembrados usan:

```text
password: password
```

Cuentas útiles:

```text
super_admin:      admin@maint.test
admin:            admin.demo@maint.test
workshop_manager: manager.north@maint.test
workshop_manager: manager.south@maint.test
advisor:          advisor.north@maint.test
advisor:          advisor.south@maint.test
technician:       technician.engine@maint.test
technician:       technician.brakes@maint.test
technician:       technician.electrical@maint.test
technician:       technician.suspension@maint.test
```

El dataset incluye owners, vehicles, workshops, technicians, vehicle systems, maintenance plans, reusable tasks, vehicle-specific tasks y maintenance orders en los principales estados del ciclo de vida.

## Variables De Entorno

Mantén `.env.example` actualizado con valores seguros. No commitees `.env`.

Valores locales importantes:

```dotenv
APP_PORT=8000
FRONTEND_PASSWORD_RESET_URL=http://localhost:5173/reset-password
API_VERSION=1.0.0
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
QUEUE_DEFAULT=default
QUEUE_EVENTS=events
QUEUE_MAIL=mail
OPERATIONS_EVENT_STREAM=ops:events
SERVICE_TOKEN_SECRET=change-me-service-token-secret-32chars
OPERATIONS_ANALYTICS_SERVICE_KEY=change-me-analytics-service-key
FORWARD_MAILPIT_SMTP_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

El stream standalone por defecto es `ops:events`. El stack completo MaintOps define `OPERATIONS_EVENT_STREAM` como `maintops:events` para que Laravel, Realtime y Analytics compartan el mismo stream.

## Arquitectura De Pruebas

Los feature tests están agrupados por área API en `tests/Feature/Api/*`, con suites adicionales para automatización de consola, eventos operativos, mail y herramientas web protegidas.

La suite cubre:

- Autenticación, logout, current-user lookup, recuperación de contraseña, usuarios inactivos, usuarios eliminados y tokens inválidos.
- CRUD con alcance por rol para users, owners, vehicles, workshops, tasks, plans, orders, order items, dashboard y audits.
- Transiciones válidas e inválidas para órdenes, items y tareas de vehículo relacionadas.
- Comandos programados de recomendación y scheduling.
- Registro y publicación transaccional de eventos operativos.
- Entrega de correos al owner.
- Contrato de initial sync de Analytics.
- Reglas de acceso a herramientas internas.

Ejecutar pruebas:

```bash
docker compose exec app php artisan test
```

La suite prioriza feature coverage porque el comportamiento importante vive en la frontera entre entrada HTTP, autorización, estado de base de datos, roles, transiciones de estado, auditoría y eventos de integración.
