# RouterOS write safety

## Current reality

S10.13 contains real RouterOS and User Manager write implementations. They are not placeholders. The checked-in portal database also contains historical real execution attempts. Any work in this area must assume production-impacting commands are reachable when configuration and guards allow them.

## Separation of responsibilities

- **Portal database:** stores local business data, safety configuration, plans, and audit outcomes.
- **RouterOS API/User Manager:** external source of operational users, profiles, sessions, counters, and access state.
- **WriteSafetyGuard:** evaluates portal-side gates and records dry-run/real attempts.
- **Controller:** validates the requested target and constructs/executes the actual RouterOS command.

## Phase 1C-B read boundary

Phase 1C-B adds a read-only boundary but does not migrate production consumers. The boundary permits only the exact RouterOS read commands already found in the application and rejects every unknown command before the low-level client is called. Its known read actions are `print` and `monitor`; action-name matching alone is not sufficient because an unknown path ending in a read-looking action is still denied.

`RealRouterOSReadGateway` receives `RouterOSClientInterface` explicitly and never constructs a client or connection. `NullRouterOSReadGateway` always throws a generic safe exception. Test fakes remain under `tests/Support` and cannot be selected through production environment configuration.

Existing writes remain on the S10.13 controller path and continue to use `WriteSafetyGuard`; no controller has migrated to the guarded boundary described below. A raw `write()` pass-through to `RouterOSApiClient::comm()` remains prohibited because it would make bypassing safety easier.

## Phase 1D-B guarded write boundary

The guarded boundary now exists and is covered by isolated unit tests, but production controllers have not migrated to it. It has no public raw `comm()` or `write()` method and no production factory. `WriteSafetyGuard::assertRealWriteAllowed()` runs inside the boundary before an authorized writer is created or the client is called.

The write policy permits only the 13 exact add/set/remove commands already used by S10.13 and validates required, allowed, and non-empty identity parameters. Read commands, reset-counters, unknown commands, missing parameters, and unexpected parameters fail closed before the client. Passwords, secrets, tokens, responses, errors, DTO results, and serialized audit details pass through centralized redaction.

Commands run in order and stop at the first failure. A failure after one or more successful calls is marked as partial; prior calls are not rolled back. One audit attempt is made after every guard-authorized operation. Guard denial retains the existing behavior of producing no real-attempt audit. If audit storage fails, the operation result remains distinct from the audit warning.

The authorized writer implementation is an anonymous class created only inside `GuardedRouterOSWriteGateway::execute()`. Production code cannot name or construct it directly. The callback receives only `AuthorizedRouterOSWriterInterface`, and the instance is invalidated when the callback ends.

### Phase 1D-C1 production migration

User Manager disable/enable is the first production write path migrated to the guarded boundary. The existing `DISABLE`/`ENABLE` confirmation token and stored dry-run plan are validated before `confirmed=true` is placed in `WriteExecutionRequest`. `GuardedRouterOSWriteGateway` is the sole real-write authorization owner for this path and records its single real-attempt audit; the controller no longer calls `assertRealWriteAllowed()` or `recordRealAttempt()`.

After authorization, the callback reads the current User Manager row, sends exactly one `/user-manager/user/set` with `numbers=<verified .id>` and `disabled=yes|no`, then reads the row again. A post-write verification mismatch is recorded as a partial failure because the set command already succeeded. No rollback is claimed. The path passes the 162-test/449-assertion isolated suite and the authorized Lab Router create/disable/enable/exact-ID-cleanup validation on identity `hAP`, RouterOS `7.23.1`, normalized model `hAP ax3`.

### Exact write policy

| Command | Required parameters | Allowed parameters | Redacted fields |
| --- | --- | --- | --- |
| `/user-manager/user/set` | `numbers` and exactly one of `disabled` or `password` | `numbers`, and exactly one of `disabled` or `password` | `password` |
| `/user-manager/limitation/set` | `numbers` | `numbers`, `transfer-limit`, `uptime-limit`, `rate-limit-rx`, `rate-limit-tx` | Sensitive-key values if introduced in nested responses |
| `/user-manager/limitation/add` | `name` | `name`, `transfer-limit`, `uptime-limit`, `rate-limit-rx`, `rate-limit-tx` | Sensitive-key values if introduced in nested responses |
| `/user-manager/profile/set` | `numbers` | `numbers`, `name-for-users`, `starts-when`, `validity`, `price` | Sensitive-key values if introduced in nested responses |
| `/user-manager/profile/add` | `name` | `name`, `name-for-users`, `starts-when`, `validity`, `price` | Sensitive-key values if introduced in nested responses |
| `/user-manager/profile-limitation/add` | `profile`, `limitation` | `profile`, `limitation` | Sensitive-key values if introduced in nested responses |
| `/user-manager/user-profile/remove` | `numbers` | `numbers` | Sensitive-key values if introduced in nested responses |
| `/user-manager/user-profile/add` | `user`, `profile` | `user`, `profile` | Sensitive-key values if introduced in nested responses |
| `/user-manager/user/add` | `name`, `password` | `name`, `password` | `password` |
| `/user-manager/session/remove` | `numbers` | `numbers` | Sensitive-key values if introduced in nested responses |
| `/user-manager/user/remove` | `numbers` | `numbers` | Sensitive-key values if introduced in nested responses |
| `/ip/hotspot/active/remove` | `numbers` | `numbers` | Sensitive-key values if introduced in nested responses |
| `/ppp/active/remove` | `numbers` | `numbers` | Sensitive-key values if introduced in nested responses |

Central redaction treats keys containing password, pass, secret, token, API key, or the exact key `key` as sensitive. It also removes known sensitive values when echoed in callback results, call responses, audit JSON, or safe exception messages. No actual values are documented or logged by this table.

### Phase 1D-C3 persistence redaction

The isolated review found that the User Manager disable/enable preview carried the complete matched RouterOS row under `backend_lookup.user_manager.matched_raw_row`. That row could reach the preview session and the `api_audit_logs.params` and `router_response` fields. The controller now keeps only the proven-safe `.id`, `name`, and `disabled` projection.

`RouterOSSensitiveDataRedactor` is the shared recursive persistence boundary. `WriteSafetyGuard` applies it before dry-run audit, real-attempt audit, or transaction-queue storage. Sensitive keys are matched case-insensitively after normalizing spaces, dots, underscores, and hyphens; values discovered under those keys are also removed when repeated elsewhere in nested results or serialized response text. `RouterOSWriteRedactor` reuses the same implementation so write execution and persistence do not maintain divergent sensitive-key rules.

This protection applies to new records only. Historical local Git objects and pre-existing operational audit records are not rewritten by this phase and must be reviewed and sanitized before any remote is added or the repository is published.

### Phase 1D-C live security evidence

The live lifecycle produced four dry-run and four successful real-attempt audits in temporary SQLite, with exactly one disable and one enable real audit. Six checkpoints—after create, disable preview, disable execution, enable preview, enable execution, and cleanup—each reported zero exact password occurrences, unredacted sensitive keys, unsafe raw structures, redactor deltas, and projection violations. Exact-ID cleanup succeeded and the complete authorized RouterOS state snapshot was unchanged.

The live audit database was separately remediated: 11 audit rows and 28 raw-row structures were sanitized without row deletion or schema change, and the post-remediation detector reports zero findings. Historical backups and old Git objects remain untrusted. The next write migration is `AdminUserManagerPasswordController`, whose acceptance gate must exercise password redaction across session state, audits, DTOs, temporary SQLite bytes, and authorized live validation.

One temporary harness incorrectly counted expected create/cleanup dry-run and real-action pairs as duplicate disable/enable audits. Actual duplicate disable and enable real-attempt counts were zero. Do not reproduce that calculation in project tests.

Changing the portal database does not necessarily change RouterOS, and a successful RouterOS command does not guarantee all intended portal updates completed.

## Required guarded sequence

1. Inspect the target using an explicitly authorized read operation.
2. Validate identifiers and build a deterministic plan.
3. Record a dry-run without sending a mutating RouterOS command.
4. Require a separate explicit confirmation for execution.
5. Verify `mikrotik_write_enabled`, safe mode policy, and backup freshness through `WriteSafetyGuard`.
6. Execute only the exact plan that was previewed.
7. Record success or failure in `api_audit_logs` without exposing secrets.
8. Re-read and reconcile state only when that external read is separately authorized.

## Safety controls

Dry-run is a planning and audit mechanism; it is not proof that RouterOS will accept the command. Confirmation establishes operator intent but does not validate the command. The backup guard checks for a recent portal backup artifact; it is not a RouterOS configuration backup and must not be treated as one. Audit logging records evidence but does not provide rollback.

Safe defaults for development and tests are: no outbound connection, real writes disabled, safe mode enabled, synthetic targets, and disposable portal data. Never toggle the checked-in database settings to obtain test coverage.

## WriteSafetyGuard decision table

The order below reflects the current code exactly:

| Method or step | Condition | Result |
| --- | --- | --- |
| `dryRunAllowed()` | `dry_run_required === 'true'` | `true`; every other value returns `false` |
| `assertDryRunAllowed()` | dry-run is not allowed | Throws `Dry Run is disabled. Enable Dry Run Required from Write Safety.` |
| `realWriteAllowed()` | write disabled, or confirmation setting is not exactly `true` | `false`; this method does not check safe mode or backups |
| `assertRealWriteAllowed()` step 1 | `mikrotik_write_enabled !== 'true'` | Throws `Real MikroTik write is blocked because MikroTik Write Enabled is OFF.` |
| step 2 | safe mode is `true` and option `allow_safe_mode` is not boolean `true` | Throws `Real MikroTik write is blocked because Safe Mode is ON.` |
| step 3 | backup guard is `true` and no fresh backup exists | Throws `Real MikroTik write is blocked because no fresh backup was found in the last 24 hours.` |
| step 4 | confirmation is required and option `confirmed` is not boolean `true` | Throws `Real MikroTik write requires explicit confirmation.` |
| final | all applicable checks pass | Returns normally; no RouterOS command is executed by the guard itself |

`latestBackup()` considers every regular file in the backup directory and selects the greatest filesystem modification time; it does not filter extensions or validate backup contents. `hasFreshBackup()` uses `current_time - file_mtime <= hours * 3600`, with a default of 24 hours. The exact boundary is fresh, and a future-dated file is also treated as fresh by the current formula.

`preflight.ready_for_real_write` is calculated as `realWriteAllowed() && hasFreshBackup()`. Consequently it remains false without a fresh file even when `backup_guard_enabled` is false, although `assertRealWriteAllowed()` can permit that same configuration. This existing difference is preserved and covered by tests.

## Current non-secret configuration snapshot

As inspected on 2026-07-22: RouterOS write was enabled, safe mode was off, dry-run and confirmation were required, backup guard was enabled, and the transaction queue was disabled. The newest repository backup was older than the guard's 24-hour freshness window. These values may change operationally; inspect them only with authorization and never modify them as a side effect of diagnostics.

## Password-change boundary

The migrated password preview stores no plaintext, reversible substitute, hash, fingerprint, length metadata, or complete RouterOS row. The operator must enter the password in the final execute POST. Plaintext is permitted only in that request's memory and in the scoped `RouterOSWriteCommand` passed to the authorized writer callback.

The guarded operation revalidates the current User Manager `.id`, sends exactly `/user-manager/user/set` with `numbers` and `password`, and re-reads the same user. Persisted plans, sessions, result DTOs, audit rows, queues, logs, views, and safe exceptions must contain only canonical redaction. Post-write lookup proves target continuity, not password readability; a continuity failure is a partial failure and does not trigger an automatic rollback.

## Prohibited shortcuts

- Calling an execute route to test reachability.
- Pointing tests at the live `.env`, portal database, or backups.
- Bypassing `WriteSafetyGuard` in a controller.
- Reusing captured customer names, IDs, credentials, or audit payloads in fixtures.
- Assuming a GET page is harmless when its controller performs RouterOS reads.
- Enabling writes, disabling safe mode, or creating a fresh backup merely to make a test pass.
- Treating a command as read-only solely because its final path segment says `print`, `get`, or `monitor`.
- Adding a production write gateway that delegates unrestricted commands directly to `comm()`.

## Incident handling

If an unexpected real attempt occurs, stop further execution, preserve portal audit evidence, record the exact time and requested action without publishing secrets, and ask the operator to inspect RouterOS state. Do not attempt an automatic compensating write.
