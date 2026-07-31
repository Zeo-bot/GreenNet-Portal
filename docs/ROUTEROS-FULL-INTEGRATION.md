# GreenNet RouterOS Operational Integration

Baseline: `36e0dce757531ad8ce70002538b50d6a1597eb02`

Target validated: Router ID 1, `Main MikroTik`, identity `hAP`, RouterOS `7.23.1 (stable)`, `arm64`.

## Operational scope

GreenNet uses the fail-closed read gateway for approved reads and the guarded write gateway for subscriber-management writes. HTTP input never supplies a RouterOS command path. Every mutation is constructed by a controller/service, validated by `WriteCommandPolicy`, and executed through a short-lived authorized writer.

Existing production operations retained:

- User Manager user create, password change, enable/disable, exact-ID delete, profile assignment/replacement, package limitation/profile push, and exact-ID session disconnect.
- Native Hotspot and PPPoE subscriber create, password/profile change, enable/disable, exact-ID delete, and exact-ID active-session disconnect.
- Router registry, backend mappings, subscriber/router migration, reconciliation state, lifecycle evaluation/enforcement, automation, and read-only control-center views.

This phase adds:

- System/network reads for Ethernet, routes, DNS, firewall-filter summary, and NAT summary.
- Real exact-ID Hotspot counter reset through the guarded gateway and Admin UI.
- Safe exact-ID removal policy for unused User Manager profiles, limitations, profile-limitations, Hotspot profiles, and PPP profiles.
- Typed native account fields for username, limits, rate, and PPP local/remote address updates.
- Stable RouterOS error codes and reconciliation flags.
- Audit metadata for correlation ID, router, backend, target, local/RouterOS IDs, before/after state, errors, and reconciliation status.
- Global CSRF enforcement for authenticated Admin POST routes.
- RouterOS API socket invalidation after trap/fatal/read failures, preventing protocol-response desynchronization.
- A repeatable live field-validation and exact-prefix cleanup CLI.

## Approved read commands

- `/system/identity/print`, `/system/resource/print`, `/system/routerboard/print`, `/system/device-mode/print`
- `/container/config/print`, `/container/print`, `/app/print`
- `/interface/print`, `/interface/ethernet/print`
- `/ip/address/print`, `/ip/route/print`, `/ip/dns/print`
- `/ip/firewall/filter/print`, `/ip/firewall/nat/print`
- `/ip/hotspot/print`, `/ip/hotspot/active/print`, `/ip/hotspot/user/print`, `/ip/hotspot/user/profile/print`
- `/interface/pppoe-server/server/print`, `/ppp/active/print`, `/ppp/secret/print`, `/ppp/profile/print`
- User Manager user/profile/limitation/assignment/session reads listed in `ReadCommandPolicy`

Unknown paths fail before reaching the RouterOS client.

## Approved write commands

User Manager:

- `/user-manager/user/add`, `/user-manager/user/set`, `/user-manager/user/remove`
- `/user-manager/user-profile/add`, `/user-manager/user-profile/remove`
- `/user-manager/profile/add`, `/user-manager/profile/set`, `/user-manager/profile/remove`
- `/user-manager/limitation/add`, `/user-manager/limitation/set`, `/user-manager/limitation/remove`
- `/user-manager/profile-limitation/add`, `/user-manager/profile-limitation/remove`
- `/user-manager/session/remove`

Native Hotspot:

- `/ip/hotspot/user/add`, `/ip/hotspot/user/set`, `/ip/hotspot/user/remove`, `/ip/hotspot/user/reset-counters`
- `/ip/hotspot/user/profile/add`, `/ip/hotspot/user/profile/set`, `/ip/hotspot/user/profile/remove`
- `/ip/hotspot/active/remove`

Native PPPoE:

- `/ppp/secret/add`, `/ppp/secret/set`, `/ppp/secret/remove`
- `/ppp/profile/add`, `/ppp/profile/set`, `/ppp/profile/remove`
- `/ppp/active/remove`

Every remove/reset target requires a non-empty exact `numbers` value. Unknown commands and extra parameters fail closed.

## Live validation

The final successful run used prefix `GN-FV-20260731093551-3fdf8a`.

User Manager results:

- Limitation create/read: pass.
- Two profile create/read operations: pass.
- Profile-limitation link: pass.
- User create/read: pass.
- Profile A assignment: pass.
- Exact-ID assignment removal and Profile B replacement: pass.
- Password change: pass.
- Disable and enable: pass.
- Final user read: pass.
- Exact-ID cleanup of assignment, user, profile-limitation, both profiles, and limitation: pass.

Counts before and after were identical:

| Dataset | Before | After |
|---|---:|---:|
| User Manager users | 6 | 6 |
| User Manager profiles | 5 | 5 |
| User Manager limitations | 6 | 6 |
| Hotspot users | 1 | 1 |
| PPP secrets | 0 | 0 |

The prefix scan across User Manager, Hotspot, and PPP tables returned zero remaining records. Generated password occurrences were zero in audit rows, raw SQLite bytes, and CLI session serialization.

An earlier run with prefix `GN-FV-20260731093302-0e8ccc` exposed the socket-desynchronization defect after a RouterOS trap. A new-connection cleanup scan found zero remaining records for that prefix. The client now disconnects after every command/read exception and has regression coverage.

Native Hotspot returned `NOT_CONFIGURED` because no Hotspot server exists. Native PPPoE returned `NOT_CONFIGURED` because no PPPoE server exists. The validator did not manufacture network topology.

## Unsupported or conditional behavior

- RouterOS 7.23.1 does not expose an immediate per-user User Manager `reset-counters` command. User statistics are monitored on the user; reset scheduling belongs to limitations. GreenNet reports this operation as unsupported instead of issuing an invented command.
- PPP secrets do not expose a safe equivalent to Hotspot user counter reset.
- Active-session disconnect is executed only when an exact current session ID exists.
- Username changes are policy-capable but conditional: GreenNet must not rename the RouterOS identity independently of its local customer identity and related history.
- Profile/limitation deletion is allowed only for an exact record proven unused by current assignments/mappings.

## Failure and reconciliation behavior

- RouterOS traps, fatal replies, timeouts, and read failures close the socket before another operation.
- Multi-command operations stop on the first failed command.
- Any failure after one successful command is marked partial and requires reconciliation; rollback is never falsely claimed.
- Local lifecycle state records `enforced`, `failed`, `router_unavailable`, `remote_missing`, or `renewal_sync_pending`.
- Passwords, RouterOS credentials, tokens, and discovered sensitive values are redacted before session/audit/queue persistence.

## Validation commands

Final automated validation:

- PHPUnit: 329 tests, 1,828 assertions; pass with no skipped or incomplete tests.
- Subscriber disposable smoke: pass.
- Hotspot portal static validation: pass (15 required artifacts).

Isolated tests:

```powershell
docker compose --env-file .env.example -f compose.test.yaml run --rm test
```

Authorized laboratory validation:

```text
php bin/routeros-field-validation.php 1
php bin/routeros-field-validation.php 1 --cleanup-prefix=GN-FV-...
```

The live command must run only with an explicitly authorized lab router, a fresh GreenNet backup, and current Write Safety authorization.
