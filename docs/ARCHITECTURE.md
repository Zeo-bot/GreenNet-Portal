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

`RouterOSClientInterface` is the low-level transport contract. Production reads use `RouterOSReadGatewayInterface`, whose real implementation applies a fail-closed command allowlist. Production writes use `GuardedRouterOSWriteGateway`, which combines `WriteSafetyGuard`, an exact command policy, centralized redaction, and one scoped `execute(request, operation)` boundary.

`RouterOSGatewayBundleFactory` is the narrow production factory for a shared lazy client plus the read and guarded-write gateways. Construction does not open a socket; only an authorized operation can trigger the lazy connection. The null read gateway fails explicitly, while network-free fakes remain under `tests/` and are available only through Composer's development autoloader.

Phase 1D-C1 migrates the User Manager disable/enable flow in `AdminMikroTikDryRunController` as the first production write consumer. The controller accepts explicit read and guarded-write gateways for tests and lazily resolves a small `RouterOSGatewayBundle` for no-argument production construction. The bundle shares one lazy `RouterOSApiClient` between `RealRouterOSReadGateway` and `GuardedRouterOSWriteGateway`; constructing the controller or bundle does not open a socket. No general service container or environment-selectable fake was added.

For this migrated flow, current-state lookup, the single `/user-manager/user/set` command, and post-write lookup execute in that order inside the guard-authorized callback. The existing `create_greennet_baseline` Portal operation remains outside the write gateway and unchanged in responsibility.

The boundary records one redacted real-attempt audit for each guard-authorized operation, including zero-command callbacks, command failures, callback failures, and partial failures. Audit failure is reported separately and does not turn a successful RouterOS result into an operation failure. No rollback is claimed.

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
2. The controller reads portal data and uses the read gateway for migrated RouterOS reads.
3. Results are normalized and rendered through a PHP view.

Guarded write flow:

1. The operator requests a preview/dry-run.
2. The controller validates inputs and records the planned action.
3. A later execute request revalidates the plan and calls the guarded write gateway.
4. The gateway enforces `WriteSafetyGuard`; only its scoped callback can issue allowed commands.
5. The gateway records the redacted attempt and outcome in the portal audit log.

There is no distributed transaction between the portal database and RouterOS. Recovery and reconciliation must account for partial success.

The current write flow is controller-planned and gateway-authorized as documented above. New write paths must not introduce an unrestricted `write()` or `comm()` shortcut.

## Phase 1D-D1 password-write migration

`AdminUserManagerPasswordController` is the second production write path migrated to the guarded boundary. Its preview performs only the User Manager lookup through `RouterOSReadGatewayInterface` and stores the minimal `.id`, `name`, and `disabled` projection. It never accepts or persists the new password.

The execute request receives and validates the password again, resolves the same lazy shared read/write bundle in production, and invokes `GuardedRouterOSWriteGatewayInterface::execute()`. Inside the authorized callback the flow is exactly read current user, require the previewed `.id`, execute one `/user-manager/user/set`, then read the user again and require the same `.id`. This verifies command completion and target continuity; RouterOS does not expose the password for comparison. No rollback is claimed after a successful command followed by a failed continuity read.

## Phase 1D-E user-create migration

`AdminUserManagerUserCreateController` now uses the shared lazy bundle for all RouterOS reads and guarded writes. Preview reads only the exact username and selected profile, stores safe user/profile projections, and never accepts a password. Final execution re-enters the password and runs the exact ordered commands `/user-manager/user/add` and `/user-manager/user-profile/add` inside one authorized callback.

The callback verifies the user is absent and profile exists before writing, then requires exactly one matching user and user-profile relation after writing. The existing local customer-package update remains ordered after RouterOS verification. Failure of the second command or post-write verification after user creation is reported as partial failure; no RouterOS rollback is claimed.

## Phase 1D-F user-delete migration

`AdminUserManagerUserDeleteController` now resolves the shared lazy read/guarded-write bundle and no longer constructs a RouterOS client, calls `comm()`, or owns the real-attempt audit. Preview stores only safe projections and exact identifiers for the matched user, associated sessions, and user-profile relations.

Execution re-reads and verifies the exact user `.id` and current child `.id` set before entering one guarded callback. The callback removes verified sessions first, verified user-profile relations second, and the verified user last; every command uses `numbers=<current exact .id>`. It stops on the first command failure, reports partial failure when any earlier removal succeeded, claims no rollback, and verifies that the user and associated children are absent afterward.

## Phase 1E operational boundary completion

The remaining operational Controllers now use the shared lazy boundaries:

- `AdminPackageAssignController`: User Manager user/profile/relation reads plus guarded ASSIGN and REPLACE.
- `AdminPackagePushController`: limitation, profile, and profile-limitation reads plus one guarded create/update/mapping operation.
- `AdminUserDisconnectController`: User Manager session, Hotspot active, and PPP active reads plus exact-ID guarded removal.
- `AdminUserManagerControlController`: read gateway only for its User Manager, Hotspot, and PPP snapshot.

Each write operation builds its current exact plan only inside the authorized callback, uses the exact command policy, stops on the first failure, reports partial success through the gateway, verifies final RouterOS state before the single real-attempt audit is finalized, and persists only safe projections.

Intentionally excluded legacy paths are non-Controller infrastructure and system/network diagnostics: `RouterSettingsService`, `MikroTikService`, `GreenNetUsageBaselineService`, and `CustomerDashboardService`. They remain scheduled separately because their responsibilities include system identity/resource data or broader service APIs; network, firewall, interfaces, routing, RouterOS upgrades, packages, system users, and factory-reset operations were not changed in Phase 1E.
