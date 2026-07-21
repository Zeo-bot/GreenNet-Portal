# RouterOS write safety

## Current reality

S10.13 contains real RouterOS and User Manager write implementations. They are not placeholders. The checked-in portal database also contains historical real execution attempts. Any work in this area must assume production-impacting commands are reachable when configuration and guards allow them.

## Separation of responsibilities

- **Portal database:** stores local business data, safety configuration, plans, and audit outcomes.
- **RouterOS API/User Manager:** external source of operational users, profiles, sessions, counters, and access state.
- **WriteSafetyGuard:** evaluates portal-side gates and records dry-run/real attempts.
- **Controller:** validates the requested target and constructs/executes the actual RouterOS command.

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

## Prohibited shortcuts

- Calling an execute route to test reachability.
- Pointing tests at the live `.env`, portal database, or backups.
- Bypassing `WriteSafetyGuard` in a controller.
- Reusing captured customer names, IDs, credentials, or audit payloads in fixtures.
- Assuming a GET page is harmless when its controller performs RouterOS reads.
- Enabling writes, disabling safe mode, or creating a fresh backup merely to make a test pass.

## Incident handling

If an unexpected real attempt occurs, stop further execution, preserve portal audit evidence, record the exact time and requested action without publishing secrets, and ask the operator to inspect RouterOS state. Do not attempt an automatic compensating write.
