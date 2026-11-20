# Self-Hosting

::: tip Don't want to self-host?
[Pricore Cloud](https://pricore.dev) runs Pricore for you.
:::

Pricore is a Laravel application. A complete installation runs four processes:

| Process | Command | Purpose |
|---------|---------|---------|
| Web server | FrankenPHP, or nginx with PHP-FPM | The web interface, the Composer API and webhooks |
| Queue worker | `php artisan horizon` | Repository syncs, archive builds, mirror imports and security scans |
| Scheduler | `php artisan schedule:run`, every minute | Mirror syncs, advisory updates and OAuth token cleanup |
| Reverb (optional) | `php artisan reverb:start` | Live sync status in the browser |

They share a database (SQLite, MySQL or PostgreSQL), Redis, and the storage directory or S3 bucket that holds the package archives.

Without Horizon running, repositories never sync. If packages don't show up after adding a repository, check that first.

## Installation

- [Docker](/self-hosting/docker) is the recommended way. The image contains everything and runs each process in its own container.
- Without Docker, install the [requirements](/self-hosting/requirements), then follow [From source](/getting-started/#from-source) and run the processes above with systemd or Supervisor.

Either way, create the first account with `php artisan pricore:install`. Sign-up is invite-only by default, so this is how you get in.

## Checklist for production

- Set `APP_URL` to the public HTTPS URL. Composer URLs, webhooks and OAuth callbacks are built from it, and passkeys only work on that domain.
- Provide your own `APP_KEY` and keep it safe. Without it, SSH keys and Git credentials can't be decrypted.
- Put a reverse proxy with TLS in front, and set `TRUSTED_PROXIES`.
- Make Pricore reachable from your Git provider, or webhooks won't arrive.
- Configure [mail](/getting-started/configuration#mail), so invitations and notifications are delivered.
- Set `HORIZON_ALLOWED_EMAILS` to reach the queue dashboard at `/horizon`.

## Backups

Back up these, together:

- **The database.** All organizations, packages, versions and tokens.
- **Package archives.** `storage/app/private` on the local disk, or your S3 bucket. A sync doesn't rebuild missing archives, and archives of older branch commits are still needed by existing lock files. Uploaded packages exist nowhere else.
- **`APP_KEY`**, and in Docker the `storage/app_key` file it was generated into.
- **The OAuth keys** `storage/oauth-private.key` and `storage/oauth-public.key`, if you use the MCP server. Without them, connected AI clients have to sign in again.

Redis only holds the queue, cache and sessions, and doesn't need a backup.

```bash
# SQLite
sqlite3 /path/to/database.sqlite ".backup backup.sqlite"

# MySQL
mysqldump -u pricore -p pricore > backup.sql

# PostgreSQL
pg_dump -U pricore pricore > backup.sql
```

## Running several servers

Pricore can run on several web servers behind a load balancer when they share:

- a MySQL or PostgreSQL database instead of SQLite,
- one Redis instance,
- an S3 bucket for archives (`DIST_DISK=s3`),
- the same `APP_KEY` and OAuth keys.

Run the scheduler on one server only. Horizon can run on several.

## Monitoring

- `GET /up` returns `200` when Laravel boots and can serve requests. The Docker image also answers `/health`, which only shows that the web server is up.
- The Horizon dashboard at `/horizon` shows queued, running and failed jobs.
- Application logs are written to `storage/logs`. In Docker, that's on the `pricore-storage` volume.
