# Operations

## Start and stop

The defined runtime consists of `greennet_php` and `greennet_nginx`. With explicit operator permission, the intended commands are:

```powershell
docker compose up -d --build
docker compose down
```

The portal is published at <http://localhost:8080>. Do not run lifecycle commands automatically. `docker compose config` is a non-lifecycle validation command, but its output may include interpolated values and must not be shared without review.

## Configuration

The repository has root and `src` environment files. PHP loads `src/.env` because `BASE_PATH` resolves to `src`; Compose itself may also consume the root `.env`. Keep real values local and never copy them into documentation, logs, issues, or test fixtures. `.env.example` contains documentation-only placeholders.

Compose currently publishes fixed port `8080`; changing `HTTP_PORT` alone does not alter that mapping.

## Portal database

The live database is `src/database/database.sqlite`. Before any authorized maintenance:

1. Confirm the resolved absolute database path.
2. Stop or coordinate writers when consistency requires it.
3. Create and verify a recoverable backup outside the test workflow.
4. Perform the smallest requested operation.
5. Verify integrity and application behavior.

Never use the live database for tests. SQLite `-wal`, `-shm`, and journal sidecars are operational state and must be handled consistently with the main database.

## Backups

`src/storage/backups/` currently contains portal database/full backup artifacts. These files may contain sensitive operational data. The Write Safety freshness check observes files in this directory, but file presence alone does not prove restorability. A portal backup is not a RouterOS configuration backup.

Backup creation, restore, cleanup, and deletion are state-changing operations and require explicit approval. Do not use tracked backups as fixtures or publish them.

## RouterOS operations

RouterOS and User Manager are external operational systems. Opening certain admin diagnostics can initiate a connection. Before an authorized operation, establish whether the requested action is portal-only, RouterOS read, dry-run, or real RouterOS write.

For writes, require the documented preview, confirmation, backup, guard, audit, and reconciliation sequence in `ROUTEROS-SAFETY.md`. Never change safety settings as an incidental operational step.

## Logs and audit

Portal application logs and `api_audit_logs` can contain operational metadata. Inspect only the minimum fields needed, redact credentials and customer identifiers, and never paste raw payloads into reports. Preserve audit evidence after failures; do not rewrite it to make an operation appear successful.

## Git and release hygiene

Ignore rules prevent new sensitive/runtime files from being added by default. The local tracking-cleanup commit removes `.env`, SQLite, and backup artifacts from new commits while leaving them on the current workstation. Their older versions still exist in local Git history.

Do not publish this repository to any remote until all affected credentials have been rotated and sensitive files have been removed from Git history through a separately approved procedure. Do not rewrite history or push as part of routine inspection.

Fresh clones will not receive the portal database or operational backups. Phase 1 must add an explicit, safe database creation/restore mechanism using schema or migrations and synthetic initial data; until then, provisioning a fresh clone is incomplete.
