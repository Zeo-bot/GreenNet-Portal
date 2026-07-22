# Testing strategy

## Current state

Phase 1A provides Composer, PHPUnit 11, disposable SQLite support, initial unit tests, and a dedicated test-only Docker runtime. There is still no CI workflow, production database provisioning mechanism, or RouterOS fake suitable for controller tests.

## Absolute isolation requirements

Future tests must not:

- open `src/database/database.sqlite`;
- read or restore anything in `src/storage/backups/`;
- load the real `.env` or `src/.env`;
- connect to MikroTik, RouterOS, User Manager, or another external service;
- start the normal application stack with production-like configuration;
- change Write Safety settings in the tracked database.

## Phase 1A test environment

- Create a unique temporary directory per test run.
- Create a new SQLite database there from an explicit test schema or migrations.
- Seed only synthetic administrators, customers, packages, and audit rows.
- Do not instantiate the RouterOS client. A fake/null client remains future work because production controllers are not yet dependency-injected.
- Provide test-only configuration with writes disabled and safe mode enabled by default.
- Delete temporary state after the run while retaining failure output that contains no secrets.

## Initial test coverage

1. PHPUnit bootstrap requires `APP_ENV=testing` and empty RouterOS credential variables.
2. Core application classes autoload without instantiating RouterOS services.
3. Each SQLite helper instance creates a random database under the container's `/tmp` tmpfs.
4. Tests write/read synthetic data, reject operational paths, and remove database files/directories.
5. A runtime assertion verifies that the container exposes only the loopback interface.

Phase 1B exercises `WriteSafetyGuard` through optional constructor dependencies for PDO, a clock callable, and a backup directory. Production construction with `new WriteSafetyGuard()` retains the original `Database` singleton, real clock, and `BASE_PATH`-derived backup location. Tests use only disposable SQLite, a temporary backup directory, and a fixed clock.

Phase 1C-B tests a read-only RouterOS boundary. A fake low-level client proves exact command, parameter, response, and exception forwarding without opening a socket. The real read gateway is tested against the complete current read-command allowlist, current write actions, and an unknown command. The null gateway must fail explicitly without disclosing an address. Test fakes are located only in `tests/Support`; an architectural assertion rejects test-fake references and write-gateway types under `src/`.

Production routes, controllers, `MikroTikService`, and other consumers have not yet migrated to this boundary. Existing controller-guarded writes remain unchanged. Testing a future write boundary is deferred until its contract enforces safety rather than forwarding arbitrary writes to `comm()`.

Phase 1D-B tests the guarded write boundary without migrating controllers or contacting RouterOS. Coverage includes every allowed command and its current parameter shape, fail-closed validation, all Write Safety denial branches, zero-command success, ordered response chaining, first-call and partial failure, callback failure, writer expiry, re-entrant execution, one audit attempt, audit-storage failure, and end-to-end password/secret/token redaction from callback results, command calls, safe exceptions, and SQLite audit rows. Architectural tests verify that the writer implementation is anonymous and gateway-scoped, no named production writer or write factory exists, and the guarded gateway exposes no raw `comm()` or `write()` method. The suite uses `FakeRouterOSClient` only under `tests/Support`, disposable SQLite, a fixed clock, and temporary synthetic backup files.

Phase 1D-C1 adds focused coverage for the migrated User Manager disable/enable controller path: preview remains non-writing; `DISABLE` and `ENABLE` preserve their exact command parameters; read-before-write and read-after-write ordering is verified; invalid confirmations and plans fail before gateway calls; guard denial makes zero RouterOS calls; set and verification failures retain distinct non-partial/partial results; and audit success, failure, and single-row ownership are verified. Tests inject gateways explicitly and use only fake RouterOS transports, disposable SQLite, a fixed clock, and temporary synthetic backup artifacts under the system temporary directory.

Phase 1D-C3 adds regression coverage for the discovered preview/audit leak. Disable and enable previews use fake User Manager rows containing a generated synthetic sentinel, then verify that the session serialization, queried audit columns, and raw temporary SQLite bytes contain zero copies. Separate tests cover dry-run, real-attempt, and transaction-queue persistence; recursive sensitive-key variants; repeated secret values in otherwise safe strings; idempotence; and preservation of `.id`, `name`, and `disabled`. No real RouterOS connection or operational database is used.

The completed Phase 1D-C verification baseline is 162 tests and 449 assertions. A separately authorized live lifecycle used temporary SQLite and session storage and evaluated six security checkpoints from creation through cleanup. At every checkpoint, exact-password, unredacted-sensitive-key, unsafe-raw-structure, redactor-delta, and projection-violation counters were zero. Temporary audits contained four dry runs and four successful real attempts, with no duplicate disable or enable real audit.

Do not preserve the temporary harness calculation that compared action names across create/cleanup dry-run and real rows; it produced a false duplicate flag unrelated to disable or enable. Future `AdminUserManagerPasswordController` coverage must explicitly verify zero password occurrences in session serialization, audit and queue fields, result DTOs, raw disposable SQLite bytes, and safe failures before any separately authorized live test.

Phase 1D-D1 adds isolated coverage for the password-change path. Synthetic sentinels verify that preview performs no write, complete RouterOS rows become only `.id`/`name`/`disabled`, execute performs exact read-set-read continuity, guard denial makes no RouterOS or audit call, command and post-write failures retain non-partial/partial semantics, and an audit-storage failure remains a successful RouterOS result with a warning. Source-level assertions preserve password re-entry, the `PASSWORD` confirmation, plan checks, no hidden/value copy, lazy construction, and absence of direct client, `comm()`, guard assertion, or controller-owned real audit calls. Live Lab Router validation remains pending and requires separate authorization.

## Running the isolated suite

Builds may use the public package network to download pinned Composer dependencies. Test runtime has no network:

```powershell
docker compose -f compose.test.yaml build test
docker compose -f compose.test.yaml run --rm test
```

Do not combine `compose.test.yaml` with the production `docker-compose.yml`. The test image copies only application PHP sources, tests, and test configuration; `.env`, `src/.env`, the runtime database, and backups are excluded from the build context.

## Additional safe checks

```powershell
git status --short
git diff --check
git diff --stat
rg --files
docker compose config
```

The test service is `read_only`, uses `/tmp` as an in-memory tmpfs, and has `network_mode: none`. The production Nginx and PHP-FPM services are not started by these commands.

## Test data policy

Fixtures must use invented usernames, RFC 5737 documentation IP addresses such as `192.0.2.1`, placeholder credentials, and non-production timestamps. Never copy rows from the live SQLite database or audit log into a fixture.
