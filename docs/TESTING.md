# Testing strategy

## Current state

The repository has no Composer manifest, PHPUnit configuration, automated test directory, or CI workflow. Phase 0 adds documentation only; it does not add or run a test framework.

## Absolute isolation requirements

Future tests must not:

- open `src/database/database.sqlite`;
- read or restore anything in `src/storage/backups/`;
- load the real `.env` or `src/.env`;
- connect to MikroTik, RouterOS, User Manager, or another external service;
- start the normal application stack with production-like configuration;
- change Write Safety settings in the tracked database.

## Proposed Phase 1 test environment

- Create a unique temporary directory per test run.
- Create a new SQLite database there from an explicit test schema or migrations.
- Seed only synthetic administrators, customers, packages, and audit rows.
- Inject a fake/null RouterOS client that records commands in memory and rejects all networking.
- Provide test-only configuration with writes disabled and safe mode enabled by default.
- Delete temporary state after the run while retaining failure output that contains no secrets.

## Initial test layers

1. Router tests for method/path dispatch and 404 behavior.
2. Database schema/migration tests against disposable SQLite.
3. `WriteSafetyGuard` tests for disabled writes, safe mode, confirmation, stale backup, dry-run recording, and audit outcomes.
4. Controller tests proving preview does not invoke a mutating client method.
5. Execute-flow tests using only a fake client, including partial failure and duplicate-submit behavior.
6. Static PHP syntax checks inside an approved isolated PHP 8.3 environment.

## Safe checks available before Phase 1

```powershell
git status --short
git diff --check
git diff --stat
rg --files
docker compose config
```

Do not run Compose lifecycle commands merely to obtain PHP lint. Container use requires explicit permission, and configuration must first be made incapable of external RouterOS access.

## Test data policy

Fixtures must use invented usernames, RFC 5737 documentation IP addresses such as `192.0.2.1`, placeholder credentials, and non-production timestamps. Never copy rows from the live SQLite database or audit log into a fixture.
