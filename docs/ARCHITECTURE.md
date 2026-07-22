# Architecture

## Runtime overview

Nginx listens on host port `8080`, serves `src/public`, and forwards PHP requests to the PHP 8.3 FPM service. `src/public/index.php` loads configuration, registers the custom autoloader and routes, and dispatches the request through the framework-free router.

```text
Browser -> Nginx -> public/index.php -> Router -> Controller -> Service/Model
                                                        |-> Portal SQLite
                                                        |-> RouterOS API (only when invoked)
```

## Portal database boundary

`src/database/database.sqlite` is the live portal database. It stores portal-owned entities such as administrators, local customers, packages, payments, settings, renewal requests, notifications, usage baselines, timeline notes, Write Safety configuration, transaction queue data, and API audit records.

The portal database is not RouterOS and is not a replica with guaranteed parity. Local database writes can occur without RouterOS writes. It must never be used as a test fixture, and application tests must use a new disposable database with synthetic data.

## RouterOS API and User Manager boundary

`src/app/Services/RouterOS` owns low-level RouterOS connectivity and read services. Administrative controllers also coordinate User Manager commands for user, profile, package, password, and session operations.

RouterOS is an external operational system. Reading it can still disclose data or load a production router; writing it can change customer access. No route, controller, diagnostic, or test that reaches this boundary may be invoked without explicit permission.

Phase 1C-B introduces contracts without migrating production call sites. `RouterOSClientInterface` exposes only the existing low-level `comm(string $command, array $params = []): array` operation. `RouterOSReadGatewayInterface` exposes only `read()`; its real implementation requires an explicitly supplied client and applies a fail-closed allowlist before delegating. It does not create a client, connection, factory, or default dependency.

The null read gateway always fails explicitly, while test fakes live under `tests/` and are available only through Composer's development autoloader. Existing controllers and services still use their original `RouterOSApiClient` paths. No production write gateway exists in 1C-B.

Phase 1C-C1 migrates `AdminUserManagerPackagesController` as the first production read path. Its optional constructor dependency accepts `RouterOSReadGatewayInterface` for tests. With no injected gateway, the narrow `RouterOSReadGatewayFactory` creates a real gateway and client only when package discovery begins; socket connection remains lazy until the first allowed read. All other direct production `comm()` calls remain legacy migration targets. There is still no production write gateway.

Phase 1D-B adds `GuardedRouterOSWriteGateway` as a tested boundary, but no controller uses it yet and there is no production write factory. The boundary receives the low-level client, `WriteSafetyGuard`, exact command policy, and redactor explicitly. Its public API exposes one scoped `execute(request, operation)` method; only the callback receives a short-lived authorized writer after the guard succeeds. The writer is invalidated when the callback ends, nested execution is denied, and no constructor opens a connection.

Phase 1D-C1 migrates the User Manager disable/enable flow in `AdminMikroTikDryRunController` as the first production write consumer. The controller accepts explicit read and guarded-write gateways for tests and lazily resolves a small `RouterOSGatewayBundle` for no-argument production construction. The bundle shares one lazy `RouterOSApiClient` between `RealRouterOSReadGateway` and `GuardedRouterOSWriteGateway`; constructing the controller or bundle does not open a socket. No general service container or environment-selectable fake was added.

For this migrated flow, current-state lookup, the single `/user-manager/user/set` command, and post-write lookup execute in that order inside the guard-authorized callback. The existing `create_greennet_baseline` Portal operation remains outside the write gateway and unchanged in responsibility. Other production write controllers remain legacy paths.

The boundary records one redacted real-attempt audit for each guard-authorized operation, including zero-command callbacks, command failures, callback failures, and partial failures. Audit failure is reported separately and does not turn a successful RouterOS result into an operation failure. No rollback is claimed. Existing controller write paths remain legacy and unchanged; disable/enable is the intended first migration.

## Write Safety boundary

`src/app/Services/WriteSafetyGuard.php` is the central policy helper for guarded writes. It manages:

- write-enabled and safe-mode checks;
- dry-run requirements;
- explicit-confirmation requirements;
- freshness of portal backup artifacts;
- audit records for dry-runs and real attempts;
- an optional transaction queue.

Controllers remain responsible for correct command planning, target validation, RouterOS responses, and post-operation behavior. The guard reduces risk but does not make a command intrinsically safe or transactional across SQLite and RouterOS.

## Request and data flow

Read flow:

1. A route selects a controller.
2. The controller reads portal data and, for RouterOS pages, may instantiate a RouterOS service/client.
3. Results are normalized and rendered through a PHP view.

Guarded write flow:

1. The operator requests a preview/dry-run.
2. The controller validates inputs and records the planned action.
3. A later execute request revalidates the plan and calls `WriteSafetyGuard`.
4. If all guards pass, the controller sends RouterOS commands.
5. The attempt and outcome are recorded in the portal audit log.

There is no distributed transaction between the portal database and RouterOS. Recovery and reconciliation must account for partial success.

The current write flow remains controller-owned and guarded as documented above. A future write boundary must encode authorization and guard sequencing; it must not be implemented as an unrestricted `write()` wrapper that delegates directly to `comm()`.

## Phase 1D-D1 password-write migration

`AdminUserManagerPasswordController` is the second production write path migrated to the guarded boundary. Its preview performs only the User Manager lookup through `RouterOSReadGatewayInterface` and stores the minimal `.id`, `name`, and `disabled` projection. It never accepts or persists the new password.

The execute request receives and validates the password again, resolves the same lazy shared read/write bundle in production, and invokes `GuardedRouterOSWriteGatewayInterface::execute()`. Inside the authorized callback the flow is exactly read current user, require the previewed `.id`, execute one `/user-manager/user/set`, then read the user again and require the same `.id`. This verifies command completion and target continuity; RouterOS does not expose the password for comparison. No rollback is claimed after a successful command followed by a failed continuity read.

## Phase 1D-E user-create migration

`AdminUserManagerUserCreateController` now uses the shared lazy bundle for all RouterOS reads and guarded writes. Preview reads only the exact username and selected profile, stores safe user/profile projections, and never accepts a password. Final execution re-enters the password and runs the exact ordered commands `/user-manager/user/add` and `/user-manager/user-profile/add` inside one authorized callback.

The callback verifies the user is absent and profile exists before writing, then requires exactly one matching user and user-profile relation after writing. The existing local customer-package update remains ordered after RouterOS verification. Failure of the second command or post-write verification after user creation is reported as partial failure; no RouterOS rollback is claimed.
