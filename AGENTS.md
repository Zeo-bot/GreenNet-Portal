# GreenNet Portal agent guide

## Project facts

- The application is custom PHP 8.3 behind Nginx, with SQLite and no framework.
- The current baseline is commit `ce9058e`, stage S10.13 (User Manager control center).
- RouterOS read/diagnostic features and real write paths are implemented. Do not describe writes as pending or dry-run-only.
- The live portal database is `src/database/database.sqlite`. Backups are under `src/storage/backups/`.

## Mandatory safety rules

- Never connect to MikroTik, RouterOS, User Manager, or any external system without explicit user permission for that specific work.
- Never run `docker compose up`, `down`, `build`, `restart`, or equivalent lifecycle commands without explicit permission.
- Never modify the live SQLite database or operational backups without explicit user permission for that exact operation.
- Never use the live SQLite database or files under `src/storage/backups/` in tests. Use a disposable fixture outside those paths.
- Never change Write Safety settings, `greennet_safe_mode`, or `mikrotik_write_enabled` unless the user explicitly requests that exact change.
- Check `git status --short` before editing. Preserve all user changes and avoid unrelated edits.
- Do not commit, push, rewrite Git history, or remove tracked files automatically.
- Do not print `.env` values, credentials, customer data, or RouterOS audit payloads.

## Safe verification commands

These commands are read-only and do not start the application or contact RouterOS:

```powershell
git status --short
git diff --check
git diff --stat
git diff -- <path>
rg --files
rg -n "pattern" src/app
docker compose config
```

`docker compose config` may expose interpolated configuration; review output carefully and do not publish it. PHP lint and automated tests should run only after an isolated test environment exists and only with explicit permission if containers are required.

The approved isolated test command is shown below, but running it still requires explicit user permission. Never combine this file with the production `docker-compose.yml`.

```powershell
docker compose --env-file .env.example -f compose.test.yaml run --rm test
```

The test runtime must retain `network_mode: none`. This allowance does not permit RouterOS access or starting, stopping, building, or otherwise operating the production Compose stack.
