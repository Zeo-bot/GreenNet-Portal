# GreenNet v1.0.0 RC2 — First Boot Checklist

Record evidence in `FIELD-VALIDATION-RESULT.md`. Do not enable WAN exposure or change existing RouterOS services during these checks.

## Runtime

- [ ] Container/app state is `running`.
- [ ] The `linux/arm64` image has loaded and extraction is complete.
- [ ] The container is not in a restart loop.
- [ ] Nginx has started.
- [ ] PHP-FPM has started.
- [ ] No fatal startup or permission errors appear in the container log.
- [ ] The web service responds on port `8080`.

## Persistent data

- [ ] `/greennet-data/database/database.sqlite` is accessible.
- [ ] Database integrity check succeeds.
- [ ] `/greennet-data/uploads` is writable and persistent.
- [ ] `/greennet-data/backups` is writable and persistent.
- [ ] A controlled container restart preserves the database, uploads, and backups.

## Application access

- [ ] `/login` is available from an authorized management client.
- [ ] Administrator login succeeds with the deliberate one-time credentials.
- [ ] Admin dashboard is available.
- [ ] Subscriber login page is available.
- [ ] Subscriber dashboard is available after authentication.
- [ ] Logout terminates the application session.

## Router and jobs

- [ ] The RouterOS API host is an explicit reachable router address, never localhost.
- [ ] The onboarding read-only connectivity check succeeds.
- [ ] Detected identity, RouterOS version, model, and architecture match the target router.
- [ ] Existing Hotspot, PPPoE, User Manager, interfaces, firewall, and NAT remain unchanged.
- [ ] The scheduler is running at the configured `300`-second interval.
- [ ] A scheduled job completes once without duplicate or runaway execution.

## Backup and handoff

- [ ] A production GreenNet backup is created through the existing backup workflow.
- [ ] Backup manifest format, database integrity, and SHA-256 are recorded.
- [ ] The backup archive is copied to operator-controlled storage.
- [ ] Sensitive bootstrap/YAML copies are protected or deleted after installation.
- [ ] Installation time, operator, selected deployment method, actual addresses, and result are recorded.

