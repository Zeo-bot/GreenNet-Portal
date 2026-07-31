# GreenNet on MikroTik RouterOS Container

This is the traditional RouterOS Container deployment path for GreenNet. It does not apply configuration automatically and it is not the RouterOS Apps YAML/bootstrap installer.

GreenNet uses one ARM64 container on constrained routers: Nginx listens on TCP 8080, PHP-FPM serves the application, and a small shell loop runs the existing automation command. SQLite, uploads, and backups share one persistent RouterOS mount. The image uses the same application and first-run bootstrap layers as the server deployment.

The commands below follow MikroTik's current [Container documentation](https://help.mikrotik.com/docs/spaces/ROS/pages/84901929/Container). Replace every `<PLACEHOLDER>` and review commands against the existing router before applying them.

## A. Build and export ARM64 on Windows or Linux

From the repository root with Docker Desktop/Engine and Buildx:

```text
docker buildx build --platform linux/arm64 --target mikrotik --file docker/production/Dockerfile.php --tag greennet/portal-mikrotik:1.0.0 --output type=docker,dest=greennet-mikrotik-arm64.tar .
```

This produces a Docker-format image archive that RouterOS can import without a public registry. To load and emulate it on Docker Desktop instead:

```text
docker buildx build --platform linux/arm64 --target mikrotik --file docker/production/Dockerfile.php --tag greennet/portal-mikrotik:1.0.0 --load .
docker image inspect greennet/portal-mikrotik:1.0.0 --format "{{.Architecture}}"
```

Upload `greennet-mikrotik-arm64.tar` to the selected RouterOS storage using Winbox, SFTP, or another deliberate transfer method.

## B. Choose storage and network values

Example values are illustrative only:

| Purpose | Placeholder | Example |
|---|---|---|
| Persistent storage root | `<STORAGE_ROOT>` | `disk1/greennet` |
| Container root filesystem | `<ROOT_DIR>` | `disk1/greennet/root` |
| Imported image archive | `<IMAGE_FILE>` | `disk1/greennet-mikrotik-arm64.tar` |
| New container bridge | `<CONTAINER_BRIDGE>` | `greennet-containers` |
| New veth | `<VETH_NAME>` | `veth-greennet` |
| RouterOS gateway | `<CONTAINER_GATEWAY_CIDR>` | `172.30.30.1/28` |
| Container address | `<CONTAINER_IP_CIDR>` | `172.30.30.2/28` |
| Gateway address | `<CONTAINER_GATEWAY>` | `172.30.30.1` |
| Container DNS | `<DNS_IP>` | a reachable trusted resolver |

Use a subnet that does not overlap LAN, VPN, WAN, Hotspot, PPPoE, or other container networks. Prefer attached USB/SSD storage; MikroTik recommends external storage for container roots and volumes.

## C. RouterOS prerequisites and isolated network

Container device mode must already be enabled. Enabling it requires RouterOS physical confirmation and is intentionally not automated:

```routeros
/system/device-mode/update container=yes
```

Create only new GreenNet-specific resources:

```routeros
/interface/bridge/add name=<CONTAINER_BRIDGE> comment="GreenNet Container only"
/ip/address/add address=<CONTAINER_GATEWAY_CIDR> interface=<CONTAINER_BRIDGE> comment="GreenNet Container gateway"
/interface/veth/add name=<VETH_NAME> address=<CONTAINER_IP_CIDR> gateway=<CONTAINER_GATEWAY>
/interface/bridge/port/add bridge=<CONTAINER_BRIDGE> interface=<VETH_NAME>
```

GreenNet is then reachable at `http://<CONTAINER_IP>:8080` from management networks that already have a route and an explicit firewall allowance. Do not attach the veth directly to a subscriber bridge unless subscriber access is intentional. Do not expose TCP 8080 to WAN.

If routed access is unavailable, an operator may add a narrowly scoped destination NAT and forward allowance bound to the router's management address and management interface/list. Those rules are site-specific and intentionally not generated here.

Internet access is not required for normal GreenNet operation after image import. If update/download access is deliberately needed, add source NAT only for `<CONTAINER_SUBNET>` and permit only the required forwarding/DNS behavior; do not add a broad firewall rewrite.

## D. Persistent mount and environment

Create one mount. Its subdirectories are initialized by GreenNet:

```routeros
/container/mounts/add list=greennet-data src=<STORAGE_ROOT>/data dst=/greennet-data
```

Create an environment list using `.env.mikrotik.example` as the value checklist. At minimum:

```routeros
/container/envs/add list=greennet-env key=APP_ENV value=production
/container/envs/add list=greennet-env key=APP_VERSION value=1.0.0
/container/envs/add list=greennet-env key=TZ value=<TIMEZONE>
/container/envs/add list=greennet-env key=DB_DATABASE value=/greennet-data/database/database.sqlite
/container/envs/add list=greennet-env key=UPLOADS_STORAGE_PATH value=/greennet-data/uploads
/container/envs/add list=greennet-env key=BACKUP_STORAGE_PATH value=/greennet-data/backups
/container/envs/add list=greennet-env key=BACKUP_RETENTION_COUNT value=5
/container/envs/add list=greennet-env key=ADMIN_USERNAME value=<UNIQUE_ADMIN_USERNAME>
/container/envs/add list=greennet-env key=ADMIN_PASSWORD value=<UNIQUE_ADMIN_PASSWORD>
/container/envs/add list=greennet-env key=SESSION_COOKIE_SECURE value=false
/container/envs/add list=greennet-env key=AUTOMATION_ENABLED value=true
/container/envs/add list=greennet-env key=AUTOMATION_INTERVAL_SECONDS value=300
```

Add support and RouterOS credentials only when needed. Do not save a filled command file containing real secrets. On first run, missing/default administrator values stop initialization. An existing database is reused without replacing its administrator.

The host RouterOS API is not `localhost` from inside the container. Register the host router using its explicit reachable address—normally `<CONTAINER_GATEWAY>`—and register other routers with their management addresses. Existing RouterOS API service restrictions and firewall policy must explicitly permit the GreenNet container IP; do not broaden them globally.

For RouterOS Apps, set `MIKROTIK_HOST=auto`; the GreenNet runtime resolves the default gateway assigned by Apps (for the validated RC2 field network, router `172.18.0.1` and container `172.18.0.2`). Keep `MIKROTIK_TIMEOUT=3`. Restrict `/ip service api` to `172.18.0.2/32` where that service restriction is dedicated to GreenNet, and place an input accept rule for `src-address=172.18.0.2 protocol=tcp dst-port=8728` immediately before the site input-drop rule. Do not add a WAN-facing API allowance.

## E. Import and start

Use a RouterOS storage path for `tmpdir` and import the local archive:

```routeros
/container/config/set tmpdir=<STORAGE_ROOT>/tmp
/container/add file=<IMAGE_FILE> interface=<VETH_NAME> root-dir=<ROOT_DIR> mountlists=greennet-data envlist=greennet-env name=greennet dns=<DNS_IP> start-on-boot=yes logging=yes memory-high=256M auto-restart-interval=30s
/container/print
```

Wait until extraction finishes and status is `stopped`, then:

```routeros
/container/start greennet
/container/print detail where name=greennet
/log/print where topics~"container"
```

Open `http://<CONTAINER_IP>:8080/admin/login`. Container logging goes to RouterOS logs; GreenNet operational history remains in SQLite.

## F. Backup, failure, and update

Before updating, create and download a GreenNet portable backup. Stop the container, import the new ARM64 archive into a new root directory, create the replacement container with the same veth, environment list, and persistent mount, then start and verify it. The entrypoint runs idempotent schema initialization; no ordinary update should recreate the bridge or veth.

If only the image/root directory is lost, recreate the container with the existing `<STORAGE_ROOT>/data` mount. If persistent storage is lost, start with new storage and restore a downloaded `.gnbackup.zip` through the admin Backup page.

If storage is absent, full, or unwritable, first-run migration or backup creation fails clearly and the existing database is not intentionally replaced. Stop the container, repair/replace the selected storage, then start again. If the container cannot start, inspect `/container/print detail` and RouterOS container logs before changing any network configuration.

This deployment never resets bridges, pools, routes, Hotspot, PPPoE, or firewall policy. The existing Router Onboarding screen now generates the final RouterOS Apps YAML or traditional `bootstrap.rsc` from operator-selected values.
