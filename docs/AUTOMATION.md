# Scheduled operations

GreenNet's runner is deployment-neutral:

```text
php bin/greennet jobs:run
php bin/greennet jobs:run lifecycle:evaluate
```

Linux cron / Docker host example (every five minutes):

```cron
*/5 * * * * cd /opt/greennet && docker compose exec -T php php /var/www/bin/greennet jobs:run
```

For Windows Task Scheduler, set the program to `php.exe`, arguments to
`bin\greennet jobs:run`, and “Start in” to the GreenNet repository directory.

For a future scheduler container, invoke the same command in an image containing
the application, Composer dependencies, `.env`, and writable database/storage
mounts.

Configuration keys:

- `AUTOMATION_ENABLED` (default `true`)
- `AUTOMATION_RETRY_DELAY_SECONDS` (default `900`)
- `AUTOMATION_STALE_LOCK_SECONDS` (default `1800`)

Run the command from only one scheduler. The database lock rejects overlap and
recovers after the configured stale-lock interval.
