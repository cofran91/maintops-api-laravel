# MaintOps Laravel API

Laravel API for MaintOps, configured to run with direct Docker Compose. You do not need PHP, Composer, MySQL, Redis, or Mailpit installed on the host machine.

Spanish documentation is available in [README.es.md](README.es.md).

## Why Direct Docker Instead Of Laravel Sail

Laravel Sail is useful when a project accepts the runtime shipped through `vendor/laravel/sail`. This project does not use Sail because the development environment must boot from a clean GitHub clone with Docker only, without local PHP or Composer, and without depending on generated files inside `vendor`.

This repository owns its runtime through `Dockerfile` and `compose.yaml`:

- `Dockerfile` installs PHP, required extensions, and Composer inside the application image.
- `compose.yaml` starts the API, MySQL, Redis, and Mailpit with stable service names.
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

## Initial Authentication

The `docker/init-development.sh` script runs migrations and seeders. It creates the base system roles and a development `super_admin` user:

```text
super_admin
admin
workshop_manager
advisor
technician
```

Development credentials:

```text
email: admin@maint.test
password: password
```

## API Documentation

Scramble generates the OpenAPI specification from routes, requests, resources, and PHPDoc blocks. The documentation UI does not regenerate the specification on each visit. Instead, `/docs` serves the pre-exported `public/api.json` file and `/docs/api.json` exposes the same file as JSON.

Regenerate the specification after changing API routes, request validation, resources, or controller PHPDocs:

```bash
docker compose exec app php artisan scramble:export
```

## Architecture Decisions

The codebase keeps the default Laravel shape recognizable, but adds a few explicit boundaries where they make engineering decisions easier to inspect:

- API routes are versioned under `routes/api/v1/*`, so future contract changes can be introduced without mixing versions in one file.
- Write workflows that coordinate persistence, state changes, related records, or audit side effects live in `app/Actions/*`. This keeps multi-step use cases visible and easy to test.
- Access rules, submitted-value constraints, and list-query scoping are kept in `app/Policies/*`, `app/Rules/*`, and `app/ModelFilters/*`. This gives each module a consistent place for authorization, validation invariants, and filtering behavior as the API grows.
- Cross-cutting or domain support code lives in `app/Support/*` and `app/Services/*`, making reusable behavior explicit instead of hiding it inside HTTP controllers.
- Shared domain vocabulary lives in `app/Enums/*` when repeated string values would otherwise spread across seeders, policies, requests, filters, actions, and tests.

These choices are intentionally modest. The project is small, so the architecture favors readable boundaries over extra layers or premature abstractions.

## Testing Architecture

Feature tests are grouped by API area under `tests/Feature/Api/*`. This mirrors the production modules and makes the test suite useful as executable documentation for each workflow.

- Authentication tests cover login, logout, current-user lookup, inactive users, deleted users, and invalid tokens.
- User-management tests are split by behavior: create, list, show, update, delete, and audit side effects.
- Data providers are used for role matrices so the same rule is exercised across `super_admin`, `admin`, `workshop_manager`, `advisor`, and `technician` without copy-paste test methods.
- `tests/Feature/Api/Users/Concerns/InteractsWithUsers.php` centralizes test fixtures for users, roles, and request payloads. Individual tests stay focused on behavior instead of setup noise.
- `RefreshDatabase` and `RolesAndAdminUserSeeder` keep each test isolated while still exercising the real migrations, seeders, roles, policies, and Sanctum token flow.
- Audit tests verify business side effects explicitly, including old and new user snapshots, instead of only checking HTTP response codes.

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
FORWARD_MAILPIT_SMTP_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

## Tests

```bash
docker compose exec app php artisan test
```
