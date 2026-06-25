# MaintOps Laravel API

Laravel API for MaintOps, configured to run with direct Docker Compose. You do not need PHP, Composer, MySQL, Redis, or Mailpit installed on the host machine.

Spanish documentation is available in [README.es.md](README.es.md).

## Platform Purpose

MaintOps models the service operation of a vehicle maintenance company. It focuses on the workflow where customers contact an advisor, report a problem or request maintenance, approve recommended work, and then take the vehicle to an assigned workshop.

The API is designed around the operational decisions behind that workflow:

- Advisors can register vehicle-specific tasks when a customer reports a concrete issue.
- Maintenance plans recommend additional activities when a vehicle is due by time or odometer.
- Owners can accept all recommended work, accept only part of it, or reject the order.
- Approved work is scheduled into workshops and technicians based on supported vehicle systems, workshop working hours, technician availability, and item duration.
- Technicians move through assigned activities while order and task states stay synchronized through state machines.
- Admin users can manage operational exceptions such as cancellations.
- Operational dashboards summarize current workload, schedule pressure, pending approvals, and role-scoped activity directly from Laravel's transactional data.

The project is intended as a portfolio backend: it emphasizes domain rules, role-based workflows, automated scheduling, state transitions, test coverage, generated API documentation, and a Docker-only developer experience.

## Operating Manual

Use the generated Scramble documentation at `/docs` for the exact HTTP contract. The notes below explain the business flow so a reviewer can understand what to try and why.

1. Prepare the operational catalog.
   Seeded vehicle systems define the service areas a workshop can support, such as engine, brakes, electrical, cooling, and tires. Workshops are configured with a manager, technicians, supported systems, city, and weekly working hours.

2. Register customers and vehicles.
   Owners represent customers. Vehicles belong to owners and keep operational data such as license plate and odometer. The odometer is important because maintenance plans can become due by mileage.

3. Create maintenance tasks and plans.
   Reusable tasks represent catalog activities, such as an oil change or brake inspection. Vehicle-specific tasks represent a customer-reported issue on a concrete vehicle. Maintenance plans group reusable tasks and define recommended intervals by days and/or kilometers.

4. Create an order.
   An advisor creates a maintenance order for a vehicle. If the customer reported a specific issue, the advisor can create a vehicle-specific task before the order is processed. The order can also start without manual tasks, relying only on plan recommendations.

5. Generate proposed order items.
   The `maintenance-orders:generate-items` command reviews created orders. It adds pending items from due maintenance plans and from active vehicle-specific tasks that have not already been included in another order for that vehicle. If items are generated, the order moves to owner approval.

6. Capture owner approval.
   The advisor contacts the owner with the recommended work. Accepted items remain available for scheduling. Rejected items are kept as part of the decision history. If every item is rejected, the order is rejected; if only some are rejected, the order becomes partially approved.

7. Schedule approved work.
   The `maintenance-orders:schedule-approved` command assigns a workshop, technician, and item times. Scheduling is day-first: for the current day it checks eligible workshops and their technicians before moving to the next day. If a technician can start the vehicle today and finish remaining items the next workday, that split is valid. If a technician has no space for the first item today, the scheduler tries another technician and then other workshops before checking tomorrow.

8. Execute the work.
   Technicians work through scheduled items. Starting an item can move the order to in progress. Completing all open items can complete the order. Vehicle-specific task status moves with the associated item, not from the task endpoint.

9. Review the operation.
   The dashboard supports cards for order status counts, current-day schedules, upcoming schedules, pending approval or scheduling work, active activities, and overdue scheduled activities. It is scoped by role, so managers and technicians see only the work they are allowed to operate.

10. Close or cancel.
   Completed orders can be delivered. Scheduled or in-progress work can be cancelled according to the state rules. Cancellations and rejections propagate through item state machines so linked vehicle tasks remain consistent.

## Roles At A Glance

- `super_admin` and `admin`: manage catalogs, users, workshops, owners, vehicles, plans, tasks, orders, and exceptional transitions.
- `advisor`: creates customer-facing operational records such as vehicle-specific tasks and maintenance orders, then manages approval-oriented transitions.
- `workshop_manager`: works inside the assigned workshop scope and can act on allowed workshop-side order or item transitions.
- `technician`: sees assigned operational work and updates executable item states.

Exact permissions are enforced by policies and request rules. The feature tests are the best executable reference for role boundaries.

## Automation

Two domain commands run every two minutes:

```text
maintenance-orders:generate-items
maintenance-orders:schedule-approved
```

They can also be run manually:

```bash
docker compose exec app php artisan maintenance-orders:generate-items
docker compose exec app php artisan maintenance-orders:schedule-approved
```

These commands are intentionally part of the domain flow instead of background decoration: they generate recommended work and turn approved work into a concrete workshop schedule.

Operational event recovery runs every minute:

```text
operational-events:dispatch
```

That command re-enqueues unpublished outbox events so temporary Redis or queue failures do not leave integrations permanently behind.

## Why Direct Docker Instead Of Laravel Sail

Laravel Sail is useful when a project accepts the runtime shipped through `vendor/laravel/sail`. This project does not use Sail because the development environment must boot from a clean GitHub clone with Docker only, without local PHP or Composer, and without depending on generated files inside `vendor`.

This repository owns its runtime through `Dockerfile` and `compose.yaml`:

- `Dockerfile` installs PHP, required extensions, and Composer inside the application image.
- `compose.yaml` starts the API, queue worker, scheduler, MySQL, Redis, and Mailpit with stable service names.
- `docker/init-development.sh` prepares `.env`, dependencies, `APP_KEY`, migrations, and seed data from inside the container.

For that reason, `laravel/sail` is not installed in this repository.

## Requirements

- Docker Desktop or Docker Engine with Docker Compose.
- Git.

## Initial Setup

Clone the repository and enter the project directory:

```bash
git clone https://github.com/cofran91/maintops-api-laravel.git
cd maintops-api-laravel
```

Build the application image and initialize the project:

```bash
docker compose build
docker compose run --rm app sh docker/init-development.sh
```

Start the services:

```bash
docker compose up -d
```

Export the OpenAPI specification used by the documentation UI:

```bash
docker compose exec app php artisan scramble:export
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

## Daily Usage

Start services:

```bash
docker compose up -d
```

Stop services:

```bash
docker compose down
```

Run Artisan commands:

```bash
docker compose exec app php artisan <command>
```

Run Composer commands:

```bash
docker compose exec app composer <command>
```

View logs:

```bash
docker compose logs -f
```

## Demo Data

The `docker/init-development.sh` script runs migrations and seeders. It creates the base system roles, a development `super_admin` user, and a portfolio demo dataset:

```text
super_admin
admin
workshop_manager
advisor
technician
```

All seeded users use the same demo password:

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

The demo dataset includes owners, vehicles, workshops, technicians, vehicle systems, maintenance plans, reusable tasks, vehicle-specific tasks, and maintenance orders across the main lifecycle states. It is intentionally small but complete enough to review role scoping, scheduling behavior, state transitions, audit records, the dashboard, and future analytics work without inventing records manually.

## API Documentation

Scramble generates the OpenAPI specification from routes, requests, resources, and PHPDoc blocks. The documentation UI does not regenerate the specification on each visit. Instead, `/docs` serves the pre-exported `public/api.json` file and `/docs/api.json` exposes the same file as JSON.

The documentation UI, the raw OpenAPI JSON, and Telescope are protected by the internal tools login at `/admin/login`. Only active users with the `super_admin` role can access those internal tools. API authentication remains token-based with Sanctum; the internal tools area uses a normal web session only for documentation and observability pages.

Regenerate the specification after changing API routes, request validation, resources, or controller PHPDocs:

```bash
docker compose exec app php artisan scramble:export
```

## Audit Trail

The API exposes a read-only audit trail for `super_admin` users. It is meant for operational review, not for public consumption: audit records include the actor, audited model, event name, old and new snapshots, request URL, IP address, user agent, tags, and timestamps.

The implementation keeps audit recording close to the workflows that own the business event. Simple model changes can still be audited by the auditing package, while aggregate changes that touch relationships or derived snapshots are recorded through explicit actions and support services.

## Operational Events

MaintOps records relevant order and item lifecycle changes in a transactional outbox before publishing them to Redis Streams. Laravel remains the source of truth: the event row is created in MySQL inside the same transaction as the business change, and the publication job is queued only after the transaction commits.

The outbox stores event metadata, aggregate information, actor, payload, targets, retry count, last error, and `published_at`. If Redis is unavailable, the event stays unpublished in MySQL and can be retried by the queue worker or by the scheduled `operational-events:dispatch` command.

Redis Streams were chosen over Redis Pub/Sub because these events are integration data, not disposable live messages. Pub/Sub only delivers to subscribers that are connected at that moment. Streams keep an ordered log that other services can read later, replay after downtime, and process with their own consumer positions. That makes the pattern better for the realtime gateway and future analytics integrations.

The stream name is configurable with `OPERATIONS_EVENT_STREAM` and defaults to:

```text
ops:events
```

The Redis connection used for streams has no Laravel key prefix, because the stream is a cross-service contract. External services should read exactly the configured stream name.

## Realtime Gateway Tokens

MaintOps can issue short-lived signed tokens for the realtime gateway after the user is authenticated with Sanctum. The token is intentionally small: it carries the user id, roles, workshop scope, audience, issued-at time, expiration time, and a unique token id. The realtime gateway can validate that signature and derive Socket.IO rooms without connecting to MySQL or knowing Laravel's session/token internals.

The signing contract is configured with `REALTIME_TOKEN_SECRET`, `REALTIME_TOKEN_TTL_SECONDS`, and `REALTIME_TOKEN_AUDIENCE`. Use a secret dedicated to realtime tokens instead of sharing `APP_KEY` with another service.

This is enough for the portfolio stack because Laravel remains the identity source and Node only validates a short-lived credential. A production deployment could harden the same boundary further with public/private key signatures, internal service credentials, mTLS, or another explicit trust contract between Laravel, Realtime, and Analytics.

## Architecture Decisions

The codebase keeps the default Laravel shape recognizable, but adds a few explicit boundaries where they make engineering decisions easier to inspect:

- API routes are versioned under `routes/api/v1/*`, so future contract changes can be introduced without mixing versions in one file.
- Write workflows that coordinate persistence, state changes, related records, or audit side effects live in `app/Actions/*`. This keeps multi-step use cases visible and easy to test.
- Scheduled operational workflows live in `app/Console/Commands/*` because they are part of the business process: order items are generated from plans and approved orders are assigned to workshops.
- Dashboard read logic lives in `app/Services/Dashboard/*`, keeping aggregation separate from HTTP controllers while still using Laravel as the transactional source of truth.
- State machines in `app/States/*` protect order, item, and vehicle-specific task lifecycles. Related state changes are nested in the transition that owns the business event.
- Operational integration events use `app/Services/OperationalEvents/*`, `app/Jobs/*`, and an outbox table so external delivery does not weaken the database transaction.
- Access rules, submitted-value constraints, and list-query scoping are kept in `app/Policies/*`, `app/Rules/*`, and `app/ModelFilters/*`. This gives each module a consistent place for authorization, validation invariants, and filtering behavior as the API grows.
- Cross-cutting or domain support code lives in `app/Support/*` and `app/Services/*`, making reusable behavior explicit instead of hiding it inside HTTP controllers.
- Internal tooling is protected separately from the API token flow: Scramble docs and Telescope use a web session restricted to active `super_admin` users.
- Shared domain vocabulary lives in `app/Enums/*` when repeated string values would otherwise spread across seeders, policies, requests, filters, actions, and tests.

These choices are intentionally modest. The project is small, so the architecture favors readable boundaries over extra layers or premature abstractions.

## Testing Architecture

Feature tests are grouped by API area under `tests/Feature/Api/*`, and operational command tests live under `tests/Feature/Console/*`. This mirrors the production modules and makes the test suite useful as executable documentation for each workflow.

- Authentication tests cover login, logout, current-user lookup, inactive users, deleted users, and invalid tokens.
- Domain tests are split by behavior: create, list, show, update, delete, relationships, audit side effects, and state transitions.
- Data providers are used for role matrices so the same rule is exercised across `super_admin`, `admin`, `workshop_manager`, `advisor`, and `technician` without copy-paste test methods.
- Test concerns centralize fixtures for users, roles, workshops, owners, vehicles, tasks, plans, and orders. Individual tests stay focused on behavior instead of setup noise.
- `RefreshDatabase` and `RolesAndAdminUserSeeder` keep each test isolated while still exercising the real migrations, seeders, roles, policies, and Sanctum token flow.
- State-machine tests verify valid and invalid transitions, role restrictions, and synchronized status changes between orders, items, and linked vehicle tasks.
- Console tests verify the automated recommendation and scheduling flow, including day-first workshop/technician selection.
- Dashboard tests verify role-scoped operational data for administrators, workshop managers, technicians, and guests.
- Audit tests verify business side effects explicitly, including old and new snapshots, instead of only checking HTTP response codes.
- Web access tests verify that internal documentation and observability pages require a `super_admin` session.

The test suite favors feature coverage because the most important behavior in this API lives at the boundary between HTTP input, authorization, database state, role assignment, and audit recording.

## Main Libraries

- `laravel/sanctum`: API authentication with personal access tokens.
- `spatie/laravel-permission`: system roles and permissions.
- `owen-it/laravel-auditing`: audit trail support for domain model changes.
- `spatie/laravel-model-states`: state machines for operational workflows.
- `tucker-eric/eloquentfilter`: declarative filtering for queries and paginated lists.
- `dedoc/scramble`: OpenAPI documentation generated from the Laravel codebase.
- `laravel/telescope`: local observability for requests, queries, jobs, logs, and events.

## Environment Variables

The `.env` file must not be committed because it can contain sensitive values.

Keep `.env.example` updated with safe example values.

Main local ports and API version:

```dotenv
APP_PORT=8000
API_VERSION=1.0.0
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
OPERATIONS_EVENT_STREAM=ops:events
FORWARD_MAILPIT_SMTP_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

## Tests

```bash
docker compose exec app php artisan test
```
