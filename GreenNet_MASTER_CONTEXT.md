# GreenNet Portal master context

Last verified: 2026-07-22 (Asia/Damascus), through phase 1C-B implementation.

## Authoritative baseline

- Branch: `master`
- Commit: `ce9058e` — `S10.13 add User Manager control center`
- Current implemented stage: S10.13
- The earlier assumption that RouterOS writes are still pending is obsolete. Real write paths have been developed and real attempts are present in the portal audit database.

## Stack and structure

- Custom PHP 8.3 application with Composer development tooling; no framework.
- Nginx serves `src/public`; PHP runs through PHP-FPM.
- SQLite portal database at `src/database/database.sqlite`.
- Application code: `src/app/Controllers`, `Core`, `Models`, `Services`, `Routes`, and `Views`.
- RouterOS integration: `src/app/Services/RouterOS` plus administrative controllers.
- Container definitions: `docker-compose.yml`, `docker/php/Dockerfile`, and `docker/nginx/default.conf`.

## Implemented capabilities

Read and diagnostic functionality includes RouterOS discovery, active users, user and profile views, API diagnostics/browser/record inspection, readiness checks, package import, customer matching, usage baselines, reports, and health/audit views.

Implemented write flows include:

- enabling and disabling User Manager users;
- creating and deleting User Manager users;
- changing User Manager passwords;
- pushing packages and assigning/replacing user packages;
- disconnecting active sessions;
- guarded RouterOS/User Manager command execution after preview/dry-run.

The portal SQLite audit data inspected on 2026-07-22 contained 30 successful dry-runs and 15 executed real-write attempts: 13 successful and 2 failed. No usernames, credentials, command parameters, or customer records are recorded in this document.

## Current Write Safety state

The following non-secret values were read from `greennet_write_safety_settings`:

| Setting | Value |
| --- | --- |
| `mikrotik_write_enabled` | `true` |
| `greennet_safe_mode` | `false` |
| `backup_guard_enabled` | `true` |
| `dry_run_required` | `true` |
| `confirm_required` | `true` |
| `transaction_queue_enabled` | `false` |

`WriteSafetyGuard` enforces configured write enablement, safe mode, explicit confirmation, a fresh-backup check, dry-run recording, and real-attempt audit recording. At inspection time the newest repository backup was older than 24 hours, so the fresh-backup guard should block new real writes. This is a time-dependent observation, not a permanent guarantee.

## Current risks and technical debt

- Real RouterOS write code exists and the checked-in database has write enabled with safe mode off.
- Live `.env` files, the portal SQLite database, and backup artifacts are no longer tracked by new commits after the local tracking cleanup. They remain present on the current workstation and their older versions remain in local Git history.
- Composer and an isolated PHPUnit suite now exist, but there is no CI pipeline or production database provisioning workflow.
- A fail-closed RouterOS read boundary exists, but production consumers still use legacy direct client calls and have not migrated to it.
- The portal database mixes application state with operational/audit data.
- Database schema creation occurs from application code rather than versioned migrations.
- Some read-facing admin pages can initiate RouterOS connections when requested.
- The root `Dockerfile` is empty; the active image definition is `docker/php/Dockerfile`.
- `HTTP_PORT` exists in environment files while Compose currently publishes fixed port `8080`.
- Development error display is enabled in the public bootstrap and requires production review.

## Proposed next stages

Completed foundation:

- Phase 1A: Composer, PHPUnit, a network-disabled test runtime, and disposable SQLite tests are complete.
- Phase 1B: `WriteSafetyGuard` has injectable test seams and its current decision behavior is covered by isolated tests.
- Phase 1C-B: `RouterOSClientInterface` and a fail-closed read-only gateway, null implementation, policy, and test fakes are complete. Production routes, controllers, and services have not migrated to this boundary yet.

Current RouterOS writes remain on the existing S10.13 controller paths and are guarded by `WriteSafetyGuard`. The current router is dedicated to lab testing and contains no real customer users; permitted test scope is GreenNet test users, packages, and sessions. Factory reset, RouterOS upgrades, and WAN, LAN, or firewall changes require a separate explicit request.

Next stages:

1. Migrate production reads gradually to `RouterOSReadGatewayInterface`, preserving responses and failure behavior.
2. Design a guarded write boundary that cannot become a raw pass-through to `comm()`.
3. Add an explicit database creation/restore mechanism for fresh clones, because runtime SQLite and backup files are not distributed through new commits.
4. Separate configuration from secrets and plan rotation/history cleanup with explicit owner approval.
5. Version database migrations and define backup/restore verification.
6. Review authentication, CSRF, production error handling, authorization, and write-flow idempotency.
7. Enable CI using only synthetic data and blocked outbound RouterOS access.

## Operating rule

Treat this file as project context, not authorization. Starting containers, contacting RouterOS, changing Write Safety, handling tracked secrets, or performing Git publication always requires the user's explicit direction.

Do not publish this repository to any remote until credentials have been rotated and sensitive files have been removed from Git history through a separately approved history-cleaning procedure. The local untracking commit does not erase older Git objects.
