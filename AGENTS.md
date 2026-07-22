# GreenNet Portal agent guide

## Project facts

- The application is custom PHP 8.3 behind Nginx, with SQLite and no framework.
- The current baseline is commit `ce9058e`, stage S10.13 (User Manager control center).
- RouterOS read/diagnostic features and real write paths are implemented. Do not describe writes as pending or dry-run-only.
- The live portal database is `src/database/database.sqlite`. Backups are under `src/storage/backups/`.

## Mandatory safety rules

- Never connect to MikroTik, RouterOS, User Manager, or any external system without explicit user permission for that specific work.
- Never run `docker compose up`, `down`, `build`, `restart`, or equivalent lifecycle commands without explicit permission.
- Never modify the live SQLite database or operational backups without explicit user permission for that exact operation.
- Never use the live SQLite database or files under `src/storage/backups/` in tests. Use a disposable fixture outside those paths.
- Never change Write Safety settings, `greennet_safe_mode`, or `mikrotik_write_enabled` unless the user explicitly requests that exact change.
- Check `git status --short` before editing. Preserve all user changes and avoid unrelated edits.
- Do not commit, push, rewrite Git history, or remove tracked files automatically.
- Do not print `.env` values, credentials, customer data, or RouterOS audit payloads.

## RouterOS boundary rules

- Do not add new production calls directly to `RouterOSApiClient::comm()`. Existing direct calls are legacy paths to migrate gradually.
- New RouterOS reads must use `RouterOSReadGatewayInterface` and its fail-closed `ReadCommandPolicy`; unknown commands must be rejected before reaching the client.
- Keep RouterOS fakes under `tests/` only. Never place a fake in `src/` or select one through production `.env` configuration.
- Do not create a write gateway that forwards raw commands directly to `comm()`. A future write boundary must preserve and enforce guarded authorization.
- Never construct or inject an authorized RouterOS writer outside `GuardedRouterOSWriteGateway`; its implementation must remain gateway-scoped.
- New write paths must not call `RouterOSClientInterface::comm()` directly. Every new write must use `execute(request, callback)` on the guarded gateway.
- `RouterOSGatewayBundleFactory` is the narrow production factory for real read and guarded-write gateways. It must never import, select, or expose test fakes.
- The bundle factory creates one shared lazy `RouterOSApiClient`; factory and gateway construction must not open a socket. Only an authorized gateway operation may trigger the client's lazy connection.
- Every new RouterOS write path must use `GuardedRouterOSWriteGateway::execute(request, callback)`; do not expose a raw write or `comm()` shortcut.
- New controllers must not receive `RouterOSClientInterface` or call `comm()` directly. Inject the appropriate read or guarded-write gateway contract instead.
- Existing direct write paths are legacy and remain scheduled for incremental migration without weakening their current safety checks.
- Never persist raw RouterOS rows or responses in sessions, audit rows, queues, or logs. Persist only the smallest safe projection required by later execution.
- All RouterOS-derived data that will be persisted must pass through `RouterOSSensitiveDataRedactor`; `WriteSafetyGuard` is the defense-in-depth persistence boundary for dry-run audits, real-attempt audits, and queued payloads.
- `RouterOSWriteRedactor` delegates to the centralized sensitive-data redactor. Do not introduce a second independent list of sensitive RouterOS keys.
- Treat historical audit data as untrusted until it has been separately scanned and sanitized under explicit authorization.
- RouterOS passwords may exist only in final execute-request memory and scoped `RouterOSWriteCommand` memory. Preview flows must never accept or persist a plaintext password.
- Never store RouterOS passwords in sessions, SQLite, audits, queues, logs, URLs, query strings, hidden inputs, HTML value attributes, DTO results, or exceptions.
- Final password execution must require password entry and validation from the current request. Post-write verification may prove target continuity only; it must never retrieve or compare the password.
- Password tests must use synthetic values only and must not include assertion output that could reveal those values.
- The disable/enable production path has completed isolated coverage and an authorized Lab Router create/disable/enable/exact-ID-cleanup validation. `AdminUserManagerPasswordController` is migrated in isolated code and remains pending separately authorized live validation.
- Password-path validation must prove zero secret occurrences in sessions, audits, result DTOs, raw temporary SQLite bytes, and the authorized live Lab Router lifecycle before final live acceptance.
- AdminUserManagerUserCreateController is a guarded production path. Its preview must not accept a password; final execution must re-enter it and use the guarded gateway for exact user creation followed by profile assignment.
- User creation must verify a missing exact username and an existing profile before writing, then verify exactly one created user and relation. A failed profile assignment after user creation is a partial failure and must never be described as rolled back.
- `AdminUserManagerUserDeleteController` is a guarded production path. It must revalidate the exact current user `.id`, collect current session and user-profile relation `.id` values, and remove only those exact identifiers inside one guarded callback.
- User deletion must stop on the first failed remove, report partial failure after any prior successful removal, never delete by wildcard or username alone, and never claim rollback.
- Existing historical backups and old Git objects remain untrusted. Do not add a remote or publish until backup scanning, required credential rotation, and separately authorized Git-history cleanup are complete.
- The current router is a lab router. Authorized testing is limited to GreenNet test users, packages, and sessions.
- Never factory-reset or upgrade RouterOS, or change WAN, LAN, or firewall configuration, without a separate explicit request for that exact operation.

## Safe verification commands

These commands are read-only and do not start the application or contact RouterOS:

```powershell
git status --short
git diff --check
git diff --stat
git diff -- <path>
rg --files
rg -n "pattern" src/app
docker compose config
```

`docker compose config` may expose interpolated configuration; review output carefully and do not publish it. PHP lint and automated tests should run only after an isolated test environment exists and only with explicit permission if containers are required.

The approved isolated test command is shown below, but running it still requires explicit user permission. Never combine this file with the production `docker-compose.yml`.

```powershell
docker compose --env-file .env.example -f compose.test.yaml run --rm test
```

The test runtime must retain `network_mode: none`. This allowance does not permit RouterOS access or starting, stopping, building, or otherwise operating the production Compose stack.
