# API Laravel de MaintOps

API Laravel para MaintOps, configurada para ejecutarse con Docker Compose directo. No necesitas instalar PHP, Composer, MySQL, Redis ni Mailpit en la maquina host.

La documentacion principal en ingles esta en [README.md](README.md).

## Proposito De La Plataforma

MaintOps modela la operacion de servicio de una empresa de mantenimiento de vehiculos. Se concentra en el flujo donde un cliente contacta a un asesor, reporta un problema o solicita mantenimiento, aprueba el trabajo recomendado y luego lleva el vehiculo a un taller asignado.

La API esta disenada alrededor de las decisiones operativas de ese flujo:

- Los asesores pueden registrar tareas especificas del vehiculo cuando el cliente reporta una falla concreta.
- Los planes de mantenimiento recomiendan actividades adicionales cuando un vehiculo cumple tiempo o kilometraje.
- El propietario puede aceptar todo el trabajo recomendado, aceptar solo una parte o rechazar la orden.
- El trabajo aprobado se programa en talleres y tecnicos segun sistemas atendidos, horarios del taller, disponibilidad del tecnico y duracion de las actividades.
- Los tecnicos avanzan sobre actividades asignadas mientras los estados de ordenes y tareas se mantienen sincronizados con maquinas de estado.
- Los usuarios administradores gestionan excepciones operativas como cancelaciones.
- Los dashboards operativos resumen carga actual, presion de agenda, aprobaciones pendientes y actividad filtrada por rol desde los datos transaccionales de Laravel.

El proyecto esta pensado como backend de portafolio: prioriza reglas de dominio, flujos por rol, programacion automatica, transiciones de estado, cobertura de pruebas, documentacion API generada y una experiencia de desarrollo solo con Docker.

## Manual Operativo

Usa la documentacion generada por Scramble en `/docs` para el contrato HTTP exacto. Las notas de abajo explican el flujo de negocio para que una persona pueda entender que probar y por que.

1. Preparar el catalogo operativo.
   Los sistemas de vehiculo sembrados definen areas de servicio que un taller puede atender, como motor, frenos, electrico, refrigeracion y llantas. Los talleres se configuran con manager, tecnicos, sistemas atendidos, ciudad y horario semanal.

2. Registrar clientes y vehiculos.
   Los propietarios representan clientes. Los vehiculos pertenecen a propietarios y guardan datos operativos como placa y kilometraje. El kilometraje importa porque los planes de mantenimiento pueden vencerse por distancia recorrida.

3. Crear tareas y planes de mantenimiento.
   Las tareas reutilizables representan actividades de catalogo, como cambio de aceite o inspeccion de frenos. Las tareas especificas de vehiculo representan una falla reportada sobre un vehiculo concreto. Los planes agrupan tareas reutilizables y definen intervalos recomendados por dias y/o kilometros.

4. Crear una orden.
   Un asesor crea una orden de mantenimiento para un vehiculo. Si el cliente reporto una falla especifica, el asesor puede crear una tarea ligada al vehiculo antes de que la orden sea procesada. La orden tambien puede iniciar sin tareas manuales, usando solo recomendaciones de planes.

5. Generar items propuestos.
   El comando `maintenance-orders:generate-items` revisa ordenes creadas. Agrega items pendientes desde planes vencidos y desde tareas activas especificas del vehiculo que no hayan sido incluidas ya en otra orden de ese vehiculo. Si se generan items, la orden pasa a aprobacion del propietario.

6. Registrar la aprobacion del propietario.
   El asesor contacta al propietario con el trabajo recomendado. Los items aceptados quedan disponibles para programacion. Los items rechazados se mantienen como historial de decision. Si todos los items se rechazan, la orden queda rechazada; si solo algunos se rechazan, queda parcialmente aprobada.

7. Programar el trabajo aprobado.
   El comando `maintenance-orders:schedule-approved` asigna taller, tecnico y horarios de items. La busqueda prioriza el dia: para el dia actual revisa talleres elegibles y sus tecnicos antes de pasar al dia siguiente. Si un tecnico puede empezar el vehiculo hoy y terminar items restantes el siguiente dia laboral, esa division es valida. Si un tecnico no tiene espacio para el primer item hoy, el programador intenta otro tecnico y luego otros talleres antes de revisar manana.

8. Ejecutar el trabajo.
   Los tecnicos trabajan sobre items programados. Iniciar un item puede mover la orden a en progreso. Completar todos los items abiertos puede completar la orden. El estado de una tarea especifica de vehiculo se mueve con su item asociado, no desde el endpoint de tareas.

9. Revisar la operacion.
   El dashboard alimenta tarjetas con conteos de ordenes por estado, agenda del dia, proximas ordenes, trabajo pendiente de aprobacion o programacion, actividades activas y actividades programadas vencidas. Respeta el alcance por rol, asi que managers y tecnicos solo ven el trabajo que pueden operar.

10. Cerrar o cancelar.
   Las ordenes completadas pueden entregarse. El trabajo programado o en progreso puede cancelarse segun las reglas de estado. Las cancelaciones y rechazos se propagan por las maquinas de estado de items para mantener consistentes las tareas especificas del vehiculo.

11. Revisar correos operativos.
   Cuando una orden se programa o se completa, Laravel encola un correo de cara al cliente solo para el propietario del vehiculo. Los correos de programacion indican cuando llevar el vehiculo al taller asignado y a que direccion; los correos de completado indican cuando y donde puede recogerlo. En el sandbox Docker, esos correos quedan capturados por Mailpit en `http://localhost:8025`. La bandeja demo queda visible intencionalmente, asi que no ingreses datos personales o de clientes reales durante las pruebas.

## Checklist De Revision

Al revisar la demo, las superficies mas utiles son:

- Contrato API: abre `/docs` con sesion `super_admin` y revisa los endpoints generados por Scramble.
- Observabilidad: abre `/telescope` con sesion `super_admin` y revisa requests, jobs, logs, queries, eventos y actividad de correos/logs.
- Sandbox de correo: abre Mailpit en `http://localhost:8025` despues de programar o completar una orden de mantenimiento y confirma que el correo operativo para el propietario fue capturado.
- Comportamiento de cola: manten el servicio `queue` corriendo, porque la publicacion de eventos y el envio de correos operativos se procesan de forma asincrona.
- Seguridad de datos: usa solo datos demo. Mailpit es una bandeja sandbox y los correos pueden ser visibles para cualquier persona con acceso a la URL de la demo.

## Roles En Resumen

- `super_admin` y `admin`: gestionan catalogos, usuarios, talleres, propietarios, vehiculos, planes, tareas, ordenes y transiciones excepcionales.
- `advisor`: crea registros operativos de cara al cliente, como tareas especificas de vehiculo y ordenes de mantenimiento, y gestiona transiciones relacionadas con aprobacion.
- `workshop_manager`: trabaja dentro del alcance de su taller asignado y puede ejecutar transiciones permitidas del lado del taller.
- `technician`: ve trabajo operativo asignado y actualiza estados ejecutables de items.

Los permisos exactos se hacen cumplir con policies y reglas de request. Las pruebas feature son la mejor referencia ejecutable de los limites por rol.

## Automatizacion

Dos comandos de dominio corren cada dos minutos:

```text
maintenance-orders:generate-items
maintenance-orders:schedule-approved
```

Tambien pueden ejecutarse manualmente:

```bash
docker compose exec app php artisan maintenance-orders:generate-items
docker compose exec app php artisan maintenance-orders:schedule-approved
```

Estos comandos son parte del flujo de dominio: generan trabajo recomendado y convierten trabajo aprobado en una agenda concreta de taller.

La recuperacion de eventos operativos corre cada minuto:

```text
operational-events:dispatch
```

Ese comando reencola eventos de outbox no publicados para que fallos temporales de Redis o de la cola no dejen las integraciones atrasadas permanentemente.

Los correos operativos de mantenimiento se envian desde Laravel mediante jobs encolados. Cuando una orden de mantenimiento se programa o se completa, la API encola un correo solo para el propietario del vehiculo con los detalles de entrega o recogida. Advisors, tecnicos y managers de taller usan las notificaciones dentro de la plataforma para sus actualizaciones operativas. Node, Realtime y Analytics no envian correos.

Los ambientes locales y sandbox usan Mailpit por defecto. Mailpit captura los correos salientes y los muestra en una bandeja web sin entregar nada a internet.

## Por Que Docker Directo En Lugar De Laravel Sail

Laravel Sail es util cuando un proyecto acepta depender del runtime publicado en `vendor/laravel/sail`. Este proyecto no usa Sail porque el entorno de desarrollo debe arrancar desde un clon limpio de GitHub usando solo Docker, sin PHP ni Composer locales, y sin depender de archivos generados dentro de `vendor`.

Este repositorio controla su runtime con `Dockerfile` y `compose.yaml`:

- `Dockerfile` instala PHP, las extensiones requeridas y Composer dentro de la imagen de la aplicacion.
- `compose.yaml` levanta la API, worker de cola, scheduler, MySQL, Redis y Mailpit con nombres de servicio estables.
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

Esta bandeja de Mailpit queda intencionalmente publica en la configuracion Docker local/sandbox. No uses correos reales, passwords, telefonos, documentos, nombres de clientes ni datos operativos privados en el ambiente demo. Cualquier correo generado por la demo puede ser visible para cualquier persona con acceso a la URL de Mailpit.

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

## Datos Demo

El script `docker/init-development.sh` ejecuta migraciones y seeders. Crea los roles base del sistema, un usuario `super_admin` para desarrollo y un dataset demo de portafolio:

```text
super_admin
admin
workshop_manager
advisor
technician
```

Todos los usuarios sembrados usan la misma contrasena demo:

```text
password: password
```

Cuentas utiles:

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

El dataset demo incluye propietarios, vehiculos, talleres, tecnicos, sistemas de vehiculo, planes de mantenimiento, tareas reutilizables, tareas especificas de vehiculo y ordenes de mantenimiento en los principales estados del ciclo de vida. Es pequeno a proposito, pero suficiente para revisar alcance por rol, programacion, transiciones de estado, auditoria, el dashboard y analitica futura sin inventar registros manualmente.

## Documentacion De La API

Scramble genera la especificacion OpenAPI desde rutas, requests, resources y bloques PHPDoc. La UI de documentacion no regenera la especificacion en cada visita. En su lugar, `/docs` sirve el archivo preexportado `public/api.json` y `/docs/api.json` expone ese mismo archivo como JSON.

La UI de documentacion, el JSON OpenAPI y Telescope estan protegidos por el login de herramientas internas en `/admin/login`. Solo usuarios activos con rol `super_admin` pueden acceder a esas herramientas internas. La autenticacion API sigue usando tokens Sanctum; el area de herramientas internas usa una sesion web normal solo para documentacion y observabilidad.

Regenera la especificacion despues de cambiar rutas API, validaciones, resources o PHPDocs de controladores:

```bash
docker compose exec app php artisan scramble:export
```

## Auditoria

La API expone una auditoria de solo lectura para usuarios `super_admin`. Esta pensada para revision operativa, no para consumo publico: los registros incluyen actor, modelo auditado, nombre del evento, snapshots anteriores y nuevos, URL de la solicitud, direccion IP, user agent, tags y fechas.

La implementacion mantiene el registro de auditoria cerca de los workflows que representan el evento de negocio. Los cambios simples de modelo pueden seguir auditados por el paquete de auditoria, mientras que los cambios agregados que tocan relaciones o snapshots derivados se registran mediante actions y servicios explicitos.

## Eventos Operativos

MaintOps registra cambios relevantes del ciclo de vida de ordenes e items en un outbox transaccional antes de publicarlos en Redis Streams. Laravel sigue siendo la fuente de verdad: la fila del evento se crea en MySQL dentro de la misma transaccion del cambio de negocio, y el job de publicacion se encola solo despues de que la transaccion confirma.

El outbox guarda metadata del evento, informacion del agregado, actor, payload, targets, contador de reintentos, ultimo error y `published_at`. Si Redis no esta disponible, el evento queda sin publicar en MySQL y puede reintentarse con el worker de cola o con el comando programado `operational-events:dispatch`.

Redis Streams se eligio sobre Redis Pub/Sub porque estos eventos son datos de integracion, no mensajes en vivo descartables. Pub/Sub solo entrega a suscriptores conectados en ese momento. Streams conserva un log ordenado que otros servicios pueden leer despues, reprocesar tras una caida y consumir con su propia posicion. Por eso encaja mejor para el gateway realtime y futuras integraciones de analitica.

El nombre del stream es configurable con `OPERATIONS_EVENT_STREAM` y por defecto es:

```text
ops:events
```

La conexion Redis usada para streams no tiene prefijo de keys de Laravel, porque el stream es un contrato entre servicios. Los servicios externos deben leer exactamente el nombre configurado.

## Sincronizacion Inicial De Analytics

Analytics no lee directamente la base de datos de Laravel. Laravel expone un snapshot interno protegido con service key para que el servicio de analitica pueda construir su propio read model antes de empezar a consumir Redis Streams.

El snapshot es paginado por cursor e incluye datos de proyeccion operativa para talleres, tecnicos, tareas de mantenimiento, ordenes e items de ordenes. Los datos de contacto, documentos, credenciales y otros datos personales de owners/usuarios se excluyen intencionalmente. La service key se configura con `OPERATIONS_ANALYTICS_SERVICE_KEY`.

## Tokens De Servicios Externos

MaintOps puede emitir tokens firmados de vida corta para servicios externos despues de que el usuario se autentica con Sanctum. El token es intencionalmente pequeno: lleva el id del usuario, roles, alcance de taller, audiencia, fecha de emision, expiracion y un id unico del token. Realtime y Analytics pueden validar esa firma sin conectarse a MySQL ni conocer los detalles internos de sesiones o tokens de Laravel.

Usa `POST /api/v1/auth/service-token` con un valor `audience` de `realtime` o `analytics`. Los tokens Analytics estan restringidos a usuarios `super_admin` y `admin`.

El contrato de firma se configura con `SERVICE_TOKEN_SECRET`, `SERVICE_TOKEN_TTL_SECONDS` y `SERVICE_TOKEN_ISSUER`. Usa un secreto dedicado para tokens de servicios externos en vez de compartir `APP_KEY` con otro servicio.

Esto es suficiente para el stack de portafolio porque Laravel sigue siendo la fuente de identidad y los servicios externos solo validan una credencial de corta duracion. Un despliegue productivo podria endurecer mas este limite con firmas de llave publica/privada, credenciales internas de servicio, mTLS u otro contrato explicito de confianza entre Laravel, Realtime y Analytics.

## Decisiones De Arquitectura

El codigo mantiene la estructura base de Laravel reconocible, pero agrega limites explicitos donde ayudan a inspeccionar decisiones de ingenieria:

- Las rutas API estan versionadas bajo `routes/api/v1/*`, para que futuros cambios de contrato puedan introducirse sin mezclar versiones en un solo archivo.
- Los workflows de escritura que coordinan persistencia, cambios de estado, registros relacionados o efectos de auditoria viven en `app/Actions/*`. Esto deja visibles los casos de uso de varios pasos y facilita probarlos.
- Los workflows operativos programados viven en `app/Console/Commands/*` porque son parte del proceso de negocio: los items se generan desde planes y las ordenes aprobadas se asignan a talleres.
- La logica de lectura del dashboard vive en `app/Services/Dashboard/*`, separando la agregacion de los controladores HTTP mientras Laravel sigue siendo la fuente transaccional de verdad.
- Las maquinas de estado en `app/States/*` protegen los ciclos de vida de ordenes, items y tareas especificas de vehiculo. Los cambios de estado relacionados se anidan en la transicion que representa el evento de negocio.
- Los eventos operativos de integracion usan `app/Services/OperationalEvents/*`, `app/Jobs/*` y una tabla outbox para que la entrega externa no debilite la transaccion de base de datos.
- Las reglas de acceso, las restricciones de valores enviados y el alcance de consultas de listado se mantienen en `app/Policies/*`, `app/Rules/*` y `app/ModelFilters/*`. Asi cada modulo tiene un lugar consistente para autorizacion, invariantes de validacion y comportamiento de filtros a medida que la API crece.
- El soporte transversal o de dominio vive en `app/Support/*` y `app/Services/*`, haciendo explicito el comportamiento reutilizable en lugar de esconderlo dentro de controladores HTTP.
- Las herramientas internas se protegen separadas del flujo de tokens API: Scramble docs y Telescope usan una sesion web restringida a usuarios activos `super_admin`.
- El vocabulario compartido de dominio vive en `app/Enums/*` cuando los valores string repetidos podrian dispersarse entre seeders, policies, requests, filters, actions y tests.

Estas decisiones son deliberadamente modestas. El proyecto es pequeno, asi que la arquitectura prioriza limites legibles sobre capas extra o abstracciones prematuras.

## Arquitectura De Pruebas

Las pruebas feature se agrupan por area API en `tests/Feature/Api/*`, y las pruebas de comandos operativos viven en `tests/Feature/Console/*`. Esto replica los modulos de produccion y hace que la suite funcione como documentacion ejecutable de cada flujo.

- Las pruebas de autenticacion cubren login, logout, consulta del usuario actual, usuarios inactivos, usuarios eliminados y tokens invalidos.
- Las pruebas de dominio estan separadas por comportamiento: crear, listar, ver, actualizar, eliminar, relaciones, efectos de auditoria y transiciones de estado.
- Los data providers cubren matrices de roles para ejercer la misma regla sobre `super_admin`, `admin`, `workshop_manager`, `advisor` y `technician` sin duplicar metodos.
- Los test concerns centralizan fixtures de usuarios, roles, talleres, propietarios, vehiculos, tareas, planes y ordenes. Cada prueba individual queda enfocada en comportamiento y no en ruido de setup.
- `RefreshDatabase` y `RolesAndAdminUserSeeder` aislan cada prueba mientras ejercitan migraciones, seeders, roles, policies y el flujo real de tokens Sanctum.
- Las pruebas de maquinas de estado verifican transiciones validas e invalidas, restricciones por rol y sincronizacion de estados entre ordenes, items y tareas especificas de vehiculo.
- Las pruebas de consola verifican el flujo automatizado de recomendacion y programacion, incluyendo seleccion por dia de taller/tecnico.
- Las pruebas de dashboard verifican datos operativos filtrados por rol para administradores, managers de taller, tecnicos e invitados.
- Las pruebas de auditoria verifican efectos de negocio explicitamente, incluyendo snapshots anteriores y nuevos, no solo codigos de respuesta HTTP.
- Las pruebas de acceso web verifican que documentacion interna y observabilidad requieran sesion `super_admin`.

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
OPERATIONS_EVENT_STREAM=ops:events
FORWARD_MAILPIT_SMTP_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

Valores por defecto para correos operativos:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Para una demo publica donde prefieras no exponer la bandeja de Mailpit, usa `MAIL_MAILER=log` y revisa la salida desde logs protegidos o Telescope. Para verificar un flujo SMTP real, configura las mismas variables con el host, puerto, usuario y password de un sandbox de Mailtrap.

## Pruebas

```bash
docker compose exec app php artisan test
```
