# GreenNet v1.0.0 RC2 — hAP ax3 Field Deployment Runbook

Target: dedicated laboratory MikroTik hAP ax3, RouterOS 7.23.1, arm64.
Release image: `ghcr.io/zeo-bot/greennet-portal-mikrotik:1.0.0-rc2` or the matching traditional ARM64 TAR.

This runbook defines the expected operator order. It does not authorize automation against the router. Choose exactly one installation method.

## Acceptance criteria

- Release artifacts and their SHA-256 checksums match.
- The target remains RouterOS 7.23.1/arm64 with Container mode enabled.
- `usb1` is persistent and has sufficient free space.
- Names `greennet`, `greennet-containers`, `veth-greennet`, `greennet-data`, and `greennet-env` do not conflict.
- `172.31.255.0/30` does not overlap a current address, route, VPN, Hotspot, PPPoE, or container network.
- No existing WAN, LAN, firewall, NAT, Hotspot, PPPoE, or User Manager configuration is replaced.
- Container/app reaches running state and stays stable.
- Port 8080 serves login, admin, and subscriber routes from an authorized management path.
- SQLite, uploads, and backups persist across a controlled restart.
- RouterOS read-only onboarding succeeds using an explicit router address, never localhost.
- Jobs, Hotspot HTTP-CHAP, subscriber/admin workflows, and backup creation pass.
- Rollback instructions are reviewed and exact created resources are recorded.

## Expected MikroTik execution order

### Common preparation

1. Record router identity, version, architecture, device mode, disk status/free space, interfaces, bridges, addresses, routes, containers/apps, mounts, env lists, firewall, NAT, Hotspot, PPPoE, and User Manager state.
2. Create and export a RouterOS backup using the operator’s normal process.
3. Verify `usb1`; create `usb1/greennet` and the required data/tmp/root subdirectories.
4. Verify artifact checksums and replace the one-time admin-password placeholder only in a protected operator copy.
5. Reconfirm no resource-name or `172.31.255.0/30` conflict.

### Traditional Container path

1. Upload the ARM64 TAR to `usb1/greennet/greennet-v1.0.0-rc2-arm64.tar`.
2. Upload the reviewed `bootstrap.rsc`.
3. Import `bootstrap.rsc` exactly once.
4. Verify the dedicated bridge, `172.31.255.1/30` gateway address, veth `172.31.255.2/30`, mount/env lists, and container record.
5. Wait until image extraction is complete and the container is stopped.
6. Start container `greennet`.
7. Inspect container state/logs and open `http://172.31.255.2:8080/admin/login` from the authorized management path.

### RouterOS Apps path

1. Confirm RouterOS can resolve and reach `ghcr.io` using existing connectivity.
2. Upload the protected `greennet-app.yaml` to `usb1/greennet/`.
3. Add the app with the RouterOS Apps `internal` network selection.
4. Observe download, extraction, and running state.
5. Record the actual `ip-address` and `ui-url` shown by RouterOS Apps; these override the requested/example address.
6. Open the actual UI URL on port 8080 and complete first boot.
7. Configure GreenNet’s RouterOS API connection using the actual reachable gateway and a dedicated API account.

### Application validation

1. Complete `FIRST-BOOT-CHECKLIST.md`.
2. Validate Hotspot, Subscriber, Admin, Jobs, and Backup sections in `FIELD-VALIDATION-CHECKLIST-AR.md`.
3. Record all evidence and deviations in `FIELD-VALIDATION-RESULT.md`.
4. Protect/delete sensitive bootstrap or YAML copies after successful handoff.

## Rollback order

### Traditional Container

1. Record current state and exact IDs before removal.
2. Stop container `greennet`.
3. Preserve `usb1/greennet/data` and copy the newest verified GreenNet backup off-router.
4. Remove only the exact container named `greennet`.
5. Remove only the exact `greennet-env` environment entries/list and `greennet-data` mount list created by this installation.
6. Remove only the exact bridge-port record attaching `veth-greennet`.
7. Remove only the exact `veth-greennet` interface.
8. Remove only the exact `172.31.255.1/30` address whose interface/comment match the GreenNet bridge.
9. Remove bridge `greennet-containers` only after proving it has no unrelated ports or configuration.
10. Do not delete `usb1/greennet/data` unless a separate explicit data-destruction decision has been made after backup verification.

### RouterOS Apps

1. Record app state and actual Apps network/UI values.
2. Stop/disable the exact GreenNet app.
3. Preserve `usb1/greennet/data` and copy the newest verified GreenNet backup off-router.
4. Remove the exact GreenNet app through RouterOS Apps without cleanup/data deletion.
5. Remove an optional NAT rule only if its exact comment and source subnet identify the rule created for this test.
6. Do not use destructive Apps cleanup or delete persistent data without a separate explicit decision.

### Router recovery

Use the pre-install RouterOS backup/export only if scoped resource removal cannot restore the prior state. Record the reason, time, evidence, and result. Never claim an automatic rollback for a partially completed RouterOS or application operation.

## Go / No-Go

- **Go:** all pre-install acceptance criteria pass and the operator has reviewed the chosen package and rollback order.
- **Stop/No-Go:** any name/address/storage conflict, checksum mismatch, missing backup, unresolved placeholder, unsupported architecture/version, unstable container, inaccessible persistent storage, or unexpected change to existing RouterOS services.
- **General production No-Go:** remains in effect until the first field result is completed and signed with no unresolved blocker.
