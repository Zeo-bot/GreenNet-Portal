# GreenNet production recovery

GreenNet backups protect GreenNet-owned operational state. They do not back up or restore MikroTik RouterOS configuration. Keep a separate RouterOS backup/export for each router.

## Create and download

1. Open **Admin → النسخ الاحتياطي والاستعادة**.
2. Select **إنشاء Backup**.
3. Download the resulting `.gnbackup.zip` package and store it as a sensitive operational secret.

The package contains a consistent SQLite snapshot, a versioned `manifest.json`, and operational files under `public/uploads`. It preserves subscribers, subscriptions, packages, payments, renewals, usage baselines, router registry and assignments, package mappings, migration history, lifecycle state, automation state, settings, and onboarding metadata.

The deployment `.env` file is not included. The manifest contains no passwords. Database-backed router connection settings remain in the SQLite snapshot for compatibility, so the whole package must still be protected as sensitive.

## Restore on a fresh installation

1. Install the same or a compatible GreenNet application version on the server, PC/VPS, or MikroTik Container.
2. Configure persistent database, upload, and backup mounts.
3. Configure deployment-specific `.env` values, networking, ports, and filesystem permissions.
4. Open the Backup page and upload the `.gnbackup.zip` package.
5. Enter `RESTORE` and confirm once.
6. GreenNet validates the package and SQLite integrity, creates an automatic safety backup of the current installation, pauses automation through its existing lock, restores the package, and verifies the restored database.
7. Re-enter or rotate deployment-specific secrets when required.
8. Confirm registered routers reconnect, package mappings are present, and subscriber records are available.
9. Resume the normal automation schedule.

If replacement fails after it begins, GreenNet attempts to restore the pre-restore database and uploads. The automatic `before_restore` package remains in configured backup storage.

## Storage and retention

- `BACKUP_STORAGE_PATH` selects persistent backup storage without embedding that host path inside packages.
- `BACKUP_RETENTION_COUNT` keeps the latest N packages.
- `php bin/greennet backup:create` exposes the same package creation service for an external scheduler.

For MikroTik Container deployment, mount the database, uploads, and backup directories on persistent storage. RouterOS networking and container configuration remain deployment-specific and are not restored by GreenNet.
