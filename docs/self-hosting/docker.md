# Docker Deployment

Deploy Pricore with Docker Compose for a simple, production-ready setup.

## Quick Start

No cloning required — just download the compose file and start:

```bash
# Download the compose file
curl -o docker-compose.yml https://raw.githubusercontent.com/pricorephp/pricore/main/docker-compose.yml

# Start Pricore
docker compose up -d

# Create your first user
docker compose exec app php artisan make:user
```

Pricore will be available at `http://localhost:8000`.

## Automatic Setup

On first boot, the entrypoint script automatically handles:

1. **APP_KEY generation** — If no `APP_KEY` is provided, one is generated and persisted to `/app/storage/app_key` (survives restarts via the storage volume)
2. **SQLite database creation** — Creates the database file if it doesn't exist
3. **Database migrations** — Runs `php artisan migrate --force` (only on the main app container, not horizon/scheduler)
4. **Cache warming** — Caches config, routes, views, and events for production performance

Horizon and the scheduler wait for the app container to be healthy before starting, ensuring migrations are complete.

## Providing Your Own APP_KEY

For production, you should provide your own `APP_KEY` via environment variable:

```bash
# Generate a key
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# Set it in your environment
APP_KEY=base64:your-generated-key docker compose up -d
```

Or create a `.env` file next to your `docker-compose.yml`:

```bash
APP_KEY=base64:your-generated-key
APP_URL=https://packages.yourcompany.com
```

## Docker Compose Services

| Service | Description | Port |
|---------|-------------|------|
| `app` | FrankenPHP web server | 8000 |
| `horizon` | Queue worker with Horizon | - |
| `scheduler` | Scheduled task runner | - |
| `redis` | Cache, sessions, and queues | - |

## Configuration

### Changing the Port

```bash
APP_PORT=9000 docker compose up -d
```

### Using External Database

For MySQL or PostgreSQL:

```bash
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=pricore
DB_USERNAME=pricore
DB_PASSWORD=your-password
```

### Using External Redis

```bash
REDIS_HOST=your-redis-host
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password
```

## Development Setup

Contributors who clone the repo can use the development compose file which builds from source and uses bind mounts:

```bash
git clone https://github.com/pricorephp/pricore.git
cd pricore
docker compose -f docker-compose.dev.yml up -d --build
```

## Reverse Proxy Setup

### Nginx

```nginx
server {
    listen 80;
    server_name packages.yourcompany.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name packages.yourcompany.com;

    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Caddy

```caddyfile
packages.yourcompany.com {
    reverse_proxy localhost:8000
}
```

### Traefik

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.pricore.rule=Host(`packages.yourcompany.com`)"
  - "traefik.http.routers.pricore.tls=true"
  - "traefik.http.routers.pricore.tls.certresolver=letsencrypt"
```

## Updating

```bash
# Pull the latest image
docker compose pull

# Restart services (migrations run automatically)
docker compose up -d
```

## Maintenance

### Viewing Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f app
docker compose logs -f horizon
```

### Database Operations

```bash
# Access tinker
docker compose exec app php artisan tinker

# Database backup (SQLite)
docker compose exec app cp /app/database/database.sqlite /app/database/backup.sqlite
```

### Cache Operations

```bash
# Clear cache
docker compose exec app php artisan cache:clear

# Clear config cache
docker compose exec app php artisan config:clear
```

## Troubleshooting

### Container Won't Start

1. Check logs: `docker compose logs app`
2. Verify environment variables are set
3. Ensure ports aren't in use
4. Check file permissions on volumes

### Database Connection Issues

1. Verify database container is healthy
2. Check DB_HOST points to the service name (e.g., `mysql` not `localhost`)
3. Confirm credentials are correct

### Redis Connection Issues

1. Verify Redis container is healthy: `docker compose exec redis redis-cli ping`
2. Check REDIS_HOST is set to `redis` (service name)
3. Review Redis logs: `docker compose logs redis`

### Queue Jobs Not Processing

1. Check Horizon status: `docker compose exec app php artisan horizon:status`
2. View Horizon logs: `docker compose logs horizon`
3. Restart Horizon: `docker compose restart horizon`
