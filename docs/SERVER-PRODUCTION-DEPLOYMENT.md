# GreenNet server production deployment

This deployment runs one immutable GreenNet application image behind Nginx. Named Docker volumes preserve SQLite, uploads, and backup packages when containers or images are replaced. Only Nginx publishes a host port.

## First installation

Prerequisites are Docker Engine or Docker Desktop with Docker Compose v2.

1. Copy the repository deployment files to the Windows PC, Linux server, or VPS.
2. Copy `.env.production.example` to `.env.production`.
3. Set a unique `ADMIN_USERNAME` and `ADMIN_PASSWORD`. Configure the HTTP port, timezone, support details, and any required RouterOS connection values.
4. Build and start:

```text
docker compose --env-file .env.production -f compose.production.yaml build
docker compose --env-file .env.production -f compose.production.yaml up -d
```

Open `http://SERVER:HTTP_PORT/admin/login`. On an empty database volume, startup creates the schema and initial administrator from the environment. Placeholder or default administrator credentials make first startup fail. An existing database is migrated and reused without replacing its administrator or data.

For public access, restrict the host firewall to the intended port and normally place GreenNet behind an HTTPS-terminating reverse proxy. Set `SESSION_COOKIE_SECURE=true` when browsers reach GreenNet through HTTPS.

## Operations

These commands work in PowerShell, Command Prompt, and Linux shells:

```text
docker compose --env-file .env.production -f compose.production.yaml ps
docker compose --env-file .env.production -f compose.production.yaml logs -f
docker compose --env-file .env.production -f compose.production.yaml down
docker compose --env-file .env.production -f compose.production.yaml up -d
docker compose --env-file .env.production -f compose.production.yaml exec php php bin/greennet backup:create
docker compose --env-file .env.production -f compose.production.yaml exec php php bin/greennet jobs:run
```

The dedicated scheduler runs the same job command against the same database and configuration every `AUTOMATION_INTERVAL_SECONDS`. Set `AUTOMATION_ENABLED=false` to leave the scheduler running without executing operational jobs. Container JSON logs rotate at 10 MB with three files.

Named volumes are the default:

- `greennet_database` → `/var/www/database`
- `greennet_uploads` → `/var/www/public/uploads`
- `greennet_backups` → `/var/www/storage/backups`

For operator-visible host storage, replace a named volume source with an absolute bind mount in a local Compose override. Ensure Docker can access the directories and the container user can write to them. Do not bind-mount application source.

## Update

1. Create and download a GreenNet backup.
2. Obtain the updated source/image definition.
3. Run the production `build` command.
4. Run the production `up -d` command. Compose recreates changed containers while retaining named volumes.
5. Check `ps` and `logs`, then open `/login` and `/admin`.

The application entrypoint runs idempotent schema migration before PHP-FPM or the scheduler starts.

## Disaster recovery

Start a fresh production deployment with a new `.env.production`, upload the `.gnbackup.zip` package on the admin Backup page, and restore it. Re-enter deployment-specific environment values and secrets, confirm existing routers reconnect, then confirm automation is enabled. GreenNet packages do not restore RouterOS configuration; keep RouterOS backup/export files separately.
