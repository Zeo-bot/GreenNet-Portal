# GreenNet Portal

GreenNet Portal is a custom PHP customer and administration portal for MikroTik Hotspot, PPP, and User Manager environments. The current repository baseline is S10.13.

## Runtime requirements

- Docker with Docker Compose
- Port `8080` available on the host
- A local `.env` and `src/.env` configured by an authorized operator

The application image uses PHP 8.3 FPM with PDO SQLite, mbstring, intl, and zip. Nginx serves `src/public`.

## Running the current application

The current Compose definition builds the PHP image and starts PHP-FPM and Nginx:

```powershell
docker compose up -d --build
```

Open <http://localhost:8080> after the services are healthy. Stop the stack with:

```powershell
docker compose down
```

These lifecycle commands can change local runtime state. Agents and automation must not run them without explicit user permission.

## Repository layout

```text
docker/                     PHP image and Nginx configuration
docs/                       Architecture, safety, testing, and operations
src/app/Controllers/        HTTP controllers
src/app/Core/               Router, configuration, database, and view core
src/app/Models/             Portal database models
src/app/Routes/web.php      HTTP route registration
src/app/Services/           Portal, Write Safety, and RouterOS services
src/app/Views/              Admin and subscriber views
src/database/               Live portal SQLite database (not a test fixture)
src/public/                 Web root and static assets
src/storage/backups/        Operational backups (not test fixtures)
```

## Safe working rules

- Do not contact MikroTik, RouterOS, User Manager, or another external system without explicit authorization.
- Do not use the live portal database or operational backups in tests.
- Do not change Write Safety, safe mode, or write enablement as part of ordinary development.
- Do not expose `.env` values, credentials, customer records, or audit payloads.
- Inspect `git status --short` before editing and preserve existing work.

See [GreenNet_MASTER_CONTEXT.md](GreenNet_MASTER_CONTEXT.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), and [docs/ROUTEROS-SAFETY.md](docs/ROUTEROS-SAFETY.md) before changing RouterOS-related code.

## Tests

No automated test suite is currently installed. Do not treat the live UI or live database as a test environment. The isolation requirements and proposed Phase 1 approach are documented in [docs/TESTING.md](docs/TESTING.md).
