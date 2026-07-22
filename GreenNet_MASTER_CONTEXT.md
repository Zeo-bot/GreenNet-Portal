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
- Phase 1C-C1: `AdminUserManagerPackagesController` is the first production read path migrated to the gateway. The migrated commands are `/user-manager/profile/print`, `/user-manager/limitation/print`, `/user-manager/profile-limitation/print`, `/user-manager/profile/limitation/print`, and `/user-manager/profile/limitations/print`. The isolated suite passes with 60 tests and 144 assertions, including lazy factory construction without a socket connection.
- Phase 1D-B: a fail-closed `GuardedRouterOSWriteGateway` boundary, exact 13-command policy, gateway-scoped anonymous writer, centralized redaction, result DTOs, and audit/partial-failure handling are implemented and isolated. No production controller or route uses the boundary yet, and no production write factory exists. The isolated suite passes with 138 tests and 319 assertions.
- Phase 1D-C1: the User Manager disable/enable flow in `AdminMikroTikDryRunController` is the first production write path migrated to the guarded boundary. Reads use `RouterOSReadGatewayInterface`; the single set uses `GuardedRouterOSWriteGatewayInterface`; production resolves both lazily through a small bundle sharing one real client. The isolated suite passes with 162 tests and 449 assertions.
- Phase 1D-C2/C5: the separately authorized Lab Router validation completed create, disable, enable, and exact-ID cleanup against identity `hAP`, RouterOS `7.23.1`, normalized model `hAP ax3`. Four temporary dry-run audits and four successful real-attempt audits were created. Disable and enable each had exactly one real audit. The test user was removed, and users, profiles, limitations, mappings, Hotspot/PPP sessions, and network state were identical before and after.
- Phase 1D-C3: the disable/enable preview was found to carry a complete User Manager row into session/audit payloads. The controller now retains only `.id`, `name`, and `disabled`; a shared recursive redactor protects dry-run audits, real-attempt audits, and queued payloads, including repeated secret values.
- Phase 1D-C4: 11 historical `api_audit_logs` rows were sanitized in place, replacing 28 unsafe raw-row structures without deleting rows or changing schema. The post-sanitization detector reports zero live findings. A sensitive ignored recovery backup is retained at `src/storage/backups/quarantine/before-audit-sanitization-20260722T083738Z.sqlite` and must never be committed or published.
- Phase 1D-C5 security revalidation evaluated six checkpoints: after create, disable preview, disable execution, enable preview, enable execution, and cleanup. Exact password occurrences, unredacted sensitive-key occurrences, unsafe raw structures, redactor deltas, and projection violations were zero at every checkpoint. A temporary harness initially misclassified expected create/cleanup dry/real action pairs as duplicates; actual duplicate disable and enable real-attempt counts were zero. This was a temporary harness defect, not an application defect, and the faulty calculation must not become a project test.
- Phase 1D-D1 migrates `AdminUserManagerPasswordController` in isolated code as the second guarded production write path. Preview no longer receives a password and retains only `.id`, `name`, and `disabled`. Execute requires password re-entry, validates the stored plan and exact current `.id`, then performs read -> `/user-manager/user/set` -> read through the shared lazy gateways with one gateway-owned audit. Plaintext is restricted to current request and authorized command memory. Live Lab Router validation is still pending and requires separate authorization.

Current RouterOS writes remain on the existing S10.13 controller paths and are guarded by `WriteSafetyGuard`. The current router is dedicated to lab testing and contains no real customer users; permitted test scope is GreenNet test users, packages, and sessions. Factory reset, RouterOS upgrades, and WAN, LAN, or firewall changes require a separate explicit request.

Next stages:

1. Complete separately authorized Lab Router validation of the migrated `AdminUserManagerPasswordController`, including password redaction checks in sessions, audits, result DTOs, raw temporary SQLite bytes, and exact-ID cleanup.
2. Continue migrating production reads gradually to `RouterOSReadGatewayInterface`, preserving responses and failure behavior.
3. Scan historical backups, rotate credentials where required, and clean sensitive Git history before any remote or publication; each operation requires explicit owner approval.
4. Add an explicit database creation/restore mechanism for fresh clones, because runtime SQLite and backup files are not distributed through new commits.
5. Separate configuration from secrets and plan credential rotation with explicit owner approval.
6. Version database migrations and define backup/restore verification.
7. Review authentication, CSRF, production error handling, authorization, and write-flow idempotency.
8. Enable CI using only synthetic data and blocked outbound RouterOS access.

## Operating rule

Treat this file as project context, not authorization. Starting containers, contacting RouterOS, changing Write Safety, handling tracked secrets, or performing Git publication always requires the user's explicit direction.

Do not publish this repository to any remote until credentials have been rotated and sensitive files have been removed from Git history through a separately approved history-cleaning procedure. The local untracking commit does not erase older Git objects.
