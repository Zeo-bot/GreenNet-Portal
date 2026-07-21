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
