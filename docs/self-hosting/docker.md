# Docker

The official image `ghcr.io/pricorephp/pricore` contains PHP, FrankenPHP and the built frontend. The compose file runs it as five containers:

| Service | Runs | Port |
|---------|------|------|
| `app` | The web interface and Composer API | 8000 |
| `horizon` | Background jobs | - |
| `scheduler` | Scheduled tasks | - |
| `reverb` | WebSockets for live updates | 8080 |
| `redis` | Queue, cache and sessions | - |

Data is kept in three volumes: `pricore-database` for the SQLite database, `pricore-storage` for archives, logs and generated keys, and `redis-data`.

## Installing

```bash
curl -o docker-compose.yml https://raw.githubusercontent.com/pricorephp/pricore/main/docker-compose.yml
```

Create a `.env` next to it. Docker Compose reads it automatically:

```bash
APP_URL=https://pricore.example.com
APP_KEY=base64:...
```

Generate the key with:

```bash
echo "base64:$(openssl rand -base64 32)"
```

Without an `APP_KEY`, the container generates one on first boot and stores it in `storage/app_key` on the volume. That works, but a key you manage yourself is easier to back up and harder to lose.

Start Pricore and create the first account:

```bash
docker compose up -d
docker compose exec app php artisan pricore:install
```

On every start, the `app` container runs the migrations and caches the configuration. On the first start it also creates the SQLite database and the OAuth keys for the [MCP server](/guide/mcp). The other containers wait until `app` is healthy.

## Configuration

The compose file passes only a few variables to the containers. To set others, such as mail or GitHub OAuth, add them to `.env` and to the `environment` list of the `app`, `horizon` and `scheduler` services. See [Configuration](/getting-started/configuration) for all variables.

To use MySQL or PostgreSQL instead of SQLite, set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` the same way. `APP_PORT` changes the port the web interface is published on.

## Reverse proxy

Run Pricore behind a proxy that terminates TLS, and set `TRUSTED_PROXIES` to the proxy's address. The proxy must forward `/app` to the Reverb container for live updates to work.

::: code-group

```caddyfile [Caddy]
pricore.example.com {
    reverse_proxy /app* localhost:8080
    reverse_proxy localhost:8000
}
```

```nginx [nginx]
server {
    listen 443 ssl;
    http2 on;
    server_name pricore.example.com;

    ssl_certificate     /etc/ssl/pricore.pem;
    ssl_certificate_key /etc/ssl/pricore.key;

    # At least ARTIFACT_MAX_SIZE, for package uploads
    client_max_body_size 64M;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /app {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }
}
```

:::

## Updating

```bash
docker compose pull
docker compose up -d
```

Migrations run automatically when the new `app` container starts. Check the [release notes](https://github.com/pricorephp/pricore/releases) before upgrading for steps that need your attention.

::: warning Compose files from before the database volume moved
Older compose files mounted `pricore-database` on `/app/database`. That hid the migrations shipped with each new image, so upgrades skipped them. The container now refuses to start with that layout.

Download the current `docker-compose.yml`, or change the `pricore-database` mounts in your own file to `/app/database/data`. The database moves along with the volume. If you set `DB_DATABASE` yourself, change it to `/app/database/data/database.sqlite`. The old `migrations`, `factories` and `seeders` directories left on the volume can be deleted; keep the `database.sqlite` file.
:::

## Common tasks

```bash
# Follow the logs of all containers
docker compose logs -f

# Run an artisan command
docker compose exec app php artisan sync:repository --all

# Back up the SQLite database
docker compose exec app php -r "(new PDO('sqlite:/app/database/data/database.sqlite'))->exec(\"VACUUM INTO '/app/database/data/backup.sqlite'\");"
```

## Troubleshooting

**Repositories don't sync.** Check that the `horizon` container is running: `docker compose ps`, then `docker compose logs horizon`.

**The containers keep restarting.** Run `docker compose logs app`. The entrypoint prints what it's doing and why it stops, for example the old volume layout described above.

**Sync status doesn't update live.** The browser must reach Reverb at `/app` on your domain. Check the proxy configuration, and the browser console for WebSocket errors.

**Links point to `localhost`.** Set `APP_URL` in `.env` and run `docker compose up -d` again.
