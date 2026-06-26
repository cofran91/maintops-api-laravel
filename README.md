# MaintOps Laravel API

Spanish documentation: [README.es.md](README.es.md).

MaintOps Laravel API is the transactional backend for a vehicle maintenance operations platform. It owns authentication, authorization, business workflows, state transitions, scheduling automation, audit records, operational events, internal integration contracts, and email delivery.

The project is designed as part of the full MaintOps portfolio stack. It can run on its own for backend review, but the complete product experience uses:

- `maintops-web-vue` for the browser console.
- `maintops-realtime-node` for live operational updates.
- `maintops-analytics-fastapi` for read-only analytics.
- `maintops-stack` for the reproducible local environment that runs all services together.

## What This Project Demonstrates

- Laravel API design with versioned routes, request validation, resources, policies, and feature tests.
- Domain modeling for owners, vehicles, workshops, technicians, vehicle systems, tasks, plans, maintenance orders, and order items.
- Role-based access control with operational scopes for administrators, advisors, workshop managers, and technicians.
- State machines for maintenance orders, order items, and vehicle-specific tasks.
- Automated operational workflows that generate recommended order items and schedule approved work.
- Transactional outbox pattern for cross-service operational events.
- Redis Streams integration without weakening Laravel's database transaction.
- Service-token issuing for Realtime and Analytics without exposing Sanctum internals to external services.
- Auditing, generated API documentation, internal observability, queued email, and Docker-only development.

## Technical Stack

| Tool | Purpose |
| --- | --- |
| Laravel 13 | Main HTTP API, console commands, queue workers, scheduler, policies, resources, and tests. |
| PHP 8.3 | Runtime used by the Docker image and CI-style validation commands. |
| Laravel Sanctum | API authentication for browser and API clients. |
| Spatie Laravel Permission | Role and permission management. |
| Spatie Laravel Model States | Explicit state machines for operational lifecycles. |
| OwenIt Laravel Auditing | Audit trail for model and workflow changes. |
| EloquentFilter | Declarative filtering for paginated list endpoints. |
| Dedoc Scramble | OpenAPI generation from Laravel routes, requests, resources, and PHPDoc. |
| Laravel Telescope | Local observability for requests, queries, jobs, logs, events, and mail activity. |
| MySQL | Transactional source of truth. |
| Redis Streams | Cross-service operational event transport. |
| Mailpit | Local email sandbox for password recovery and owner-facing operational emails. |

## MaintOps Ecosystem Role

Laravel is the source of truth for identity, authorization, transactional data, and business decisions.

- The Vue console authenticates against Laravel and calls the versioned API.
- The Realtime gateway receives short-lived Laravel-issued service tokens and consumes Laravel events from Redis Streams.
- The Analytics API receives short-lived Laravel-issued service tokens, imports Laravel snapshots through an internal service-key endpoint, and keeps its own read model updated from the same Redis Stream.
- Mailpit captures emails locally so password recovery and owner notifications can be reviewed without external SMTP credentials.

External services do not connect to Laravel's MySQL database and do not reuse Sanctum tokens directly. They validate explicit service tokens or service keys created for their integration boundary.

## Project Structure

The codebase keeps Laravel's default shape recognizable and adds explicit boundaries where the domain benefits from them:

```text
app/
  Actions/          Multi-step write workflows and aggregate updates.
  Console/Commands/ Scheduled operational automation.
  Enums/            Shared domain vocabulary for roles, statuses, and system codes.
  Http/Controllers/ Versioned API controllers and internal web-tool controllers.
  Http/Requests/    Request validation and submitted-value constraints.
  Http/Resources/   API response serialization.
  Jobs/             Queued event publication and mail delivery.
  Mail/             Owner-facing operational email templates.
  ModelFilters/     Query filtering for list endpoints.
  Models/           Eloquent models for transactional data.
  Notifications/    Password recovery notification delivery.
  Policies/         Authorization rules for API resources.
  Rules/            Reusable domain validation rules.
  Services/         Cross-cutting and integration services.
  States/           State machines for orders, order items, and vehicle tasks.
  Support/          Shared helpers such as explicit audit recording.
routes/
  api.php           Version prefix and route module loading.
  api/v1/           Versioned API route files by domain area.
  console.php       Scheduler definitions.
database/
  migrations/       Transactional schema.
  seeders/          Roles, base data, and portfolio demo data.
tests/
  Feature/          API, console, operational event, and internal-tool tests.
```

Controllers stay thin. Business decisions that coordinate persistence, state changes, audit side effects, or integration events live in actions, state classes, services, console commands, and jobs so those decisions are easy to inspect and test.

## Domain Model And Roles

MaintOps models the service operation of a vehicle maintenance company:

- Owners represent customers.
- Vehicles belong to owners and keep operational data such as license plate and odometer.
- Vehicle systems describe workshop capabilities such as engine, brakes, electrical, cooling, and tires.
- Workshops have managers, technicians, supported systems, city, address, and weekly working hours.
- Maintenance tasks can be reusable catalog tasks or vehicle-specific customer-reported issues.
- Maintenance plans group reusable tasks and define recommended intervals by days and/or kilometers.
- Maintenance orders group work requested for a vehicle.
- Maintenance order items represent individual activities that can be approved, scheduled, started, completed, rejected, or cancelled.

Main roles:

- `super_admin` and `admin`: manage catalogs, users, workshops, owners, vehicles, plans, tasks, orders, and exceptional transitions.
- `advisor`: creates customer-facing operational records and manages approval-oriented transitions.
- `workshop_manager`: operates inside an assigned workshop scope.
- `technician`: sees assigned operational work and updates executable item states.

Exact permissions are enforced by policies, request rules, and tests.

## Operational Workflow

1. Prepare the catalog with vehicle systems, workshops, users, reusable tasks, and maintenance plans.
2. Register owners and vehicles.
3. Create a maintenance order for a vehicle, optionally with vehicle-specific customer-reported tasks.
4. Run `maintenance-orders:generate-items` to add due plan tasks and active vehicle-specific tasks to created orders.
5. Capture owner approval. Accepted items remain available for scheduling; rejected items remain part of the decision history.
6. Run `maintenance-orders:schedule-approved` to assign workshop, technician, and planned times based on capability, working hours, availability, and item duration.
7. Technicians start and complete scheduled items. Related order and task states move through state machines.
8. Completed orders can be delivered. Scheduled or in-progress work can be cancelled according to state rules.
9. Dashboard and audit endpoints expose role-scoped operational visibility.

Two domain commands run every two minutes through the scheduler:

```text
maintenance-orders:generate-items
maintenance-orders:schedule-approved
```

Operational event recovery runs every minute:

```text
operational-events:dispatch --limit=100
```

## Operational Events

MaintOps records relevant order and item lifecycle changes in a transactional outbox before publishing them to Redis Streams. The event row is created in MySQL inside the same transaction as the business change, and the publication job is queued only after the transaction commits.

Redis Streams are used instead of Redis Pub/Sub because these events are integration data, not disposable live messages. Streams keep an ordered log that Realtime and Analytics can consume with independent consumer groups, recover after downtime, and process at their own pace.

The stream name is configurable with `OPERATIONS_EVENT_STREAM`. In the full stack, all services use:

```text
maintops:events
```

If Redis is unavailable, the event remains unpublished in MySQL and can be retried by the queue worker or by the scheduled recovery command.

## Analytics Integration

Analytics does not read the Laravel database directly. Laravel exposes an internal, service-key protected initial sync endpoint:

```text
GET /api/v1/internal/analytics/initial-sync/{resource}
```

The snapshot is cursor-paginated and includes projection data for workshops, technicians, maintenance tasks, maintenance orders, and maintenance order items. Contact details, documents, credentials, and other owner/user PII are intentionally excluded.

The shared service key is configured with `OPERATIONS_ANALYTICS_SERVICE_KEY`.

## External Service Tokens

Authenticated users can request short-lived service tokens for external services:

```text
POST /api/v1/auth/service-token
```

Supported audiences:

- `realtime`: used by the Node Socket.IO gateway.
- `analytics`: used by the FastAPI Analytics API and restricted to administrative roles.

The token carries only the user id, roles, workshop scope, audience, issued-at time, expiration time, and unique token id. Realtime and Analytics validate the token with `SERVICE_TOKEN_SECRET` and do not need access to MySQL or Laravel's Sanctum internals.

## Email And Password Recovery

Laravel queues two email flows:

- Password recovery emails generated by the public auth endpoints.
- Owner-facing operational emails when a maintenance order is scheduled or completed.

Operational emails are sent only to the vehicle owner. Advisors, technicians, and workshop managers receive operational updates in the platform through realtime notifications instead of email.

Local and demo environments use Mailpit by default. Mailpit captures outbound email and exposes it in a browser inbox without sending anything to the internet:

```text
http://localhost:8025
```

Use only demo data. Mailpit is visible to anyone with access to the local/demo URL.

## API Documentation And Observability

Scramble generates the OpenAPI specification from routes, requests, resources, and PHPDoc blocks. The documentation UI serves the exported `public/api.json` file.

Protected internal tools:

- `/admin/login`: web login for internal tools.
- `/docs`: Scramble documentation UI.
- `/docs/api.json`: exported OpenAPI JSON.
- `/telescope`: local observability dashboard.

These internal tools require a normal web session and an active `super_admin` user. API authentication remains token-based with Sanctum.

Regenerate the OpenAPI file after changing routes, requests, resources, or controller PHPDocs:

```bash
docker compose exec app php artisan scramble:export
```

## Run Standalone With Docker

The recommended way to review the complete product is `maintops-stack`. Use the standalone Compose file when you want to focus on the Laravel API by itself.

Requirements:

- Docker Engine or Docker Desktop with Docker Compose.
- Git.

Clone and initialize:

```bash
git clone https://github.com/cofran91/maintops-api-laravel.git
cd maintops-api-laravel
docker compose build
docker compose run --rm app sh docker/init-development.sh
docker compose up -d
```

The API is available at:

```text
http://localhost:8000
```

Mailpit is available at:

```text
http://localhost:8025
```

If local port `3306` is already in use, change this value in `.env`:

```dotenv
FORWARD_DB_PORT=3307
```

The application inside Docker still connects to MySQL with `DB_HOST=mysql` and `DB_PORT=3306`.

## Useful Commands

```bash
docker compose up -d
docker compose down
docker compose logs -f
docker compose exec app php artisan <command>
docker compose exec app composer <command>
```

Run domain automation manually:

```bash
docker compose exec app php artisan maintenance-orders:generate-items
docker compose exec app php artisan maintenance-orders:schedule-approved
docker compose exec app php artisan operational-events:dispatch --limit=100
```

Run queue workers in the standalone environment:

```bash
docker compose logs -f queue queue-events queue-mail scheduler
```

## Demo Data

`docker/init-development.sh` runs migrations and seeders. It creates roles, a development `super_admin` user, and a portfolio demo dataset.

All seeded users use:

```text
password: password
```

Useful accounts:

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

The dataset includes owners, vehicles, workshops, technicians, vehicle systems, maintenance plans, reusable tasks, vehicle-specific tasks, and maintenance orders across the main lifecycle states.

## Environment Variables

Keep `.env.example` updated with safe example values. Do not commit `.env`.

Important local values:

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

The standalone default stream is `ops:events`. The full MaintOps stack sets `OPERATIONS_EVENT_STREAM` to `maintops:events` so Laravel, Realtime, and Analytics share the same stream.

## Testing Architecture

Feature tests are grouped by API area under `tests/Feature/Api/*`, with additional suites for console automation, operational events, mail, and protected web tools.

The suite covers:

- Authentication, logout, current-user lookup, password recovery, inactive users, deleted users, and invalid tokens.
- Role-scoped CRUD behavior for users, owners, vehicles, workshops, tasks, plans, orders, order items, dashboard, and audits.
- Valid and invalid state transitions for orders, items, and linked vehicle tasks.
- Scheduled recommendation and scheduling commands.
- Transactional operational event recording and publishing.
- Owner-facing mail delivery.
- Analytics initial sync contract.
- Internal tools access rules.

Run tests:

```bash
docker compose exec app php artisan test
```

The test suite favors feature coverage because the important behavior lives at the boundary between HTTP input, authorization, database state, role assignment, state transitions, audit records, and integration events.
