# GreenNet v1.0.0 RC1 — Production Readiness

Audit date: 2026-07-31  
Audited commit: `67f246267cefec557bedf9a94f04b3e82e64d501`  
RC1 image source commit/tag: `d8c2ce8d40f0bdb5b70cc15c2183d1301f531b54` / `greennet-v1.0.0-rc1`  
Target: MikroTik hAP ax3, RouterOS 7.23.1, arm64

## Executive decision

**Conditional Go for the first controlled field deployment.** The repository and corrected deployment artifacts contain no known build-time blocker. Promotion to general production remains **No-Go** until the field checklist is completed and signed, including first boot, persistence, RouterOS connectivity, Hotspot HTTP-CHAP, subscriber/admin workflows, backup creation, and rollback review.

## Deployment audit

| Path/artifact | Result | Notes |
|---|---|---|
| Server Compose | Pass | Separate PHP, scheduler, and Nginx services; persistent database/uploads/backups volumes; backend network is internal; port defaults to 8080. |
| MikroTik ARM64 image | Pass (previously verified) | `linux/arm64`; RC1 listens on 8080; traditional TAR retained outside the repository release directory. |
| Traditional Container | Pass after artifact correction | hAP ax3 package now consistently uses `usb1/greennet`, `172.31.255.1/30`, `172.31.255.2/30`, port 8080, container `greennet`, and 256M memory-high. |
| RouterOS Apps | Pass with field risk | Fully qualified public GHCR reference, `usb1/greennet/data`, port 8080, no NAT/WAN rule. RouterOS Apps controls the effective app network/UI URL. |
| `bootstrap.rsc` | Pass with mandatory operator gate | Fail-fast checks protect existing veth/container/mount/env names. It creates only the dedicated bridge, address, veth, mount, env list, and container. Password placeholder must be replaced before import. |
| Backup strategy | Pass | Existing portable backup includes manifest, integrity result, and checksums; a fresh backup is mandatory after first boot. Restore is not part of initial installation. |
| Rollback | Pass as documentation | Apps package has rollback instructions; Traditional rollback requires exact resource IDs/names and data preservation. It must be rehearsed/reviewed in the field. |
| Hotspot Portal | Pass static validation | Local assets only; HTTP-CHAP, PAP fallback, trial, redirects, Arabic error mapping, optional Subscriber Portal URL, Walled Garden instructions, and file checksums are present. |

## Corrected release artifact

The pre-audit Traditional package mixed device-generic defaults (`disk1/greennet`, `172.30.30.0/28`) with the hAP ax3 deployment. It was corrected and repackaged at:

`C:\Users\Zeo\GreenNet-Releases\greennet-v1.0.0-rc1\greennet-v1.0.0-rc1-hap-ax3-installation-package.zip`

The package now includes `SHA256SUMS.txt`. This correction changes deployment artifacts only; it does not change application or RouterOS behavior and was not applied to hardware.

- Field Batch 1 Traditional ZIP SHA-256: `1c660167fee4214e788bd2d8f4597361c421899273370037fc3aa78721460c88`
- ARM64 TAR SHA-256: `e3bd446784294e7f1def5422050fdbd967170b73f4d99ed66e0712f779092618`
- Local image: `linux/arm64`, ID `sha256:8bbd0c74c922cc346cd6c9f1c70bd998ed2768c349006c74fd207aca162664f8`, size 40,820,423 bytes

## Repository production-blocker scan

### Classified as expected, not blockers

- `localhost` in the developer README/operations guide is the PC-local Docker endpoint.
- `127.0.0.1` in Nginx health checks, smoke fixtures, and MikroTik Nginx-to-PHP-FPM is container/test loopback, not the RouterOS API host.
- `CHANGE_ME_*`, `<SET-ONE-TIME-ADMIN-PASSWORD>`, and RouterOS credential markers are deliberate templates. Production entrypoints reject default admin credentials. Installation must stop until operator placeholders are replaced.
- `disk1` and `172.30.30.0/28` in `docs/MIKROTIK-CONTAINER-DEPLOYMENT.md`, `.env.mikrotik.example`, onboarding defaults, and unit tests are generic examples/defaults. They are not the hAP ax3 release values. The device-specific package is authoritative.
- The example `portal.example.com` appears only in Hotspot instructions; runtime `subscriberPortalUrl` is empty and the link stays hidden until deliberately configured.
- The duplicate GreenNet logo under the Hotspot package and public media is intentional packaging: the Hotspot bundle must have no external dependency.
- No broken local Markdown references were found.
- No tracked file names indicated an accidental copy, obsolete backup, debug dump, or temporary release asset.
- No `TODO`, `FIXME`, or `console.log` production blocker was found in the audited source/deployment paths.

### Documentation debt (non-blocking)

- `docs/MIKROTIK-CONTAINER-DEPLOYMENT.md` and `.env.mikrotik.example` are generic templates and can be mistaken for the device-specific hAP ax3 package. Operators must use the generated release package, not copy example values.
- Historical milestones and test counts in `docs/TESTING.md` are retained as history; the current validation result supersedes those counts.
- Some RouterOS safety text describes incremental migrations historically. The guarded production paths documented in the current agent/release baseline are authoritative.

## Hotspot review details

- HTTP-CHAP submits `MD5(chap-id + password + chap-challenge)` through the hidden RouterOS form and clears the visible password.
- RouterOS variables used by login, status, logout, redirect, trial, and captive portal JSON are present.
- Trial markup is guarded by `$(if trial == 'yes')`.
- Redirect pages use RouterOS-provided links.
- Common authentication, disabled-account, expiry/quota, concurrent-session, and RADIUS errors have Arabic messages.
- CSS, JavaScript, MD5, and logo files are local; no CDN, font service, or runtime third-party asset is referenced.
- Runtime rejects Subscriber Portal URLs using localhost/127.0.0.1 and hides an empty/invalid configuration.
- Walled Garden configuration is documented but intentionally not applied automatically.

## Known risks and assumptions

1. RouterOS Apps assigns/controls the effective app network. The YAML `page` value may not equal the UI URL shown by RouterOS. Use the Apps UI URL as authoritative and set GreenNet’s RouterOS API host to the actual reachable gateway.
2. The GHCR tag `1.0.0-rc1` is mutable. Record the pulled image digest during field validation; use the immutable RC1 tag/digest for later reproduction.
3. `usb1` availability, health, filesystem behavior, free capacity, and persistence across reboot require physical-device validation.
4. The proposed `172.31.255.0/30` network was checked only against stored discovery from the prior preparation. Reconfirm read-only immediately before import because live state may have changed.
5. RouterOS API service/firewall must permit only the container/app source address. No package broadens API or WAN exposure automatically.
6. The one-time administrator password exists in the reviewed bootstrap/YAML copy during installation. Protect and delete that artifact after use.
7. Hotspot PAP is inherently plaintext at the application layer unless the client transport is protected. Prefer HTTP-CHAP and validate the active Hotspot profile.
8. No live restore has been executed as part of readiness. Backup integrity is necessary but does not replace a separately controlled restoration rehearsal.

## Completed validations

- Deployment file and instruction cross-check across Server, Traditional Container, RouterOS Apps, and Hotspot paths.
- Fully qualified GHCR image reference and arm64 metadata review.
- Storage, port, container name, paths, scheduler interval, and RouterOS API-host consistency review.
- Placeholder/localhost/development marker classification.
- Hotspot RouterOS-variable, HTTP-CHAP, trial, redirect, translation, local-dependency, and checksum review.
- Duplicate tracked-file hash review and local Markdown reference check.
- Traditional hAP ax3 package correction and checksum/ZIP regeneration.
- Traditional package rollback instructions and the unified field deployment runbook.
- Hotspot static validation: pass (15 required artifacts).
- Isolated PHPUnit baseline: pass (261 tests, 1713 assertions; no skipped or incomplete tests reported).
- Field Batch 1 subscriber disposable smoke: pass (boot, local assets, authentication, subscriber APIs, ownership isolation, renewal, logout, and PWA contracts).
- Field Batch 1 artifact validation: pass (Traditional/Apps/Hotspot checksums, JSON values, required YAML values, ZIP readability, and local `linux/arm64` image metadata).

## Pending field validations

- Current live conflict inventory immediately before installation.
- USB health/capacity/persistence and image extraction on hAP ax3.
- GHCR pull from RouterOS Apps and effective Apps UI/network values.
- First boot, logs, database/uploads/backups persistence, and controlled restart.
- Admin, subscriber, Router API, jobs, Hotspot, and backup workflows.
- Rollback review or controlled rehearsal and operator/release-owner sign-off.

## Release gate

- **First controlled deployment:** Go, provided the operator completes all pre-install gates and stops on any value conflict.
- **General production release:** No-Go until `FIELD-VALIDATION-RESULT.md` records a passing field run with no unresolved blocker.
