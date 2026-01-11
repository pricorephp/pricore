# Docker Deployment

Deploy Pricore with Docker Compose for a simple, production-ready setup.

## Quick Start

```bash
# Clone the repository
git clone https://github.com/pricorephp/pricore.git
cd pricore

# Copy environment file
cp .env.example .env

# Generate APP_KEY
php -r "echo 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
# Add this to your .env file

# Start containers
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate

# Create first user
docker compose exec app php artisan make:user
```

## Docker Compose Services

The default `docker-compose.yml` includes:

| Service | Description | Port |
|---------|-------------|------|
| `app` | FrankenPHP web server | 8000 |
| `horizon` | Queue worker with Horizon | - |
| `scheduler` | Scheduled task runner | - |
| `redis` | Cache, sessions, and queues | 6379 |

## Service Configuration

### App Service

The main web application:

```yaml
app:
  build:
    context: .
    dockerfile: Dockerfile
  container_name: pricore-app
  restart: unless-stopped
  ports:
    - "8000:8000"
  volumes:
    - ./storage:/app/storage
    - ./database:/app/database
  environment:
    - APP_ENV=${APP_ENV:-production}
    - APP_DEBUG=${APP_DEBUG:-false}
    - APP_KEY=${APP_KEY}
    - APP_URL=${APP_URL:-http://localhost:8000}
    - DB_CONNECTION=${DB_CONNECTION:-sqlite}
    - DB_DATABASE=${DB_DATABASE:-/app/database/database.sqlite}
    - REDIS_HOST=redis
    - REDIS_PORT=6379
    - CACHE_STORE=redis
    - SESSION_DRIVER=redis
    - QUEUE_CONNECTION=redis
  depends_on:
    redis:
      condition: service_healthy
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
    interval: 30s
    timeout: 3s
    retries: 3
```

### Horizon Service

Queue worker for background jobs:

```yaml
horizon:
  build:
    context: .
    dockerfile: Dockerfile
  container_name: pricore-horizon
  restart: unless-stopped
  command: php artisan horizon
  stop_signal: SIGTERM
  stop_grace_period: 30s
  volumes:
    - ./storage:/app/storage
    - ./database:/app/database
  # ... same environment as app
  healthcheck:
    test: ["CMD", "php", "artisan", "horizon:status"]
    interval: 30s
    timeout: 10s
    retries: 3
```

### Scheduler Service

Runs scheduled tasks every minute:

```yaml
scheduler:
  build:
    context: .
    dockerfile: Dockerfile
  container_name: pricore-scheduler
  restart: unless-stopped
  command: sh -c "while true; do php artisan schedule:run --verbose; sleep 60; done"
  volumes:
    - ./storage:/app/storage
    - ./database:/app/database
  # ... same environment as app
```

### Redis Service

```yaml
redis:
  image: redis:7-alpine
  container_name: pricore-redis
  restart: unless-stopped
  command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru
  ports:
    - "6379:6379"
  volumes:
    - redis-data:/data
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 10s
    timeout: 3s
    retries: 3
```

## Environment Configuration

### Required Variables

```bash
# Application
APP_KEY=base64:...  # Generate with php -r "echo base64_encode(random_bytes(32));"
APP_URL=https://packages.yourcompany.com

# Database (SQLite by default)
DB_CONNECTION=sqlite
DB_DATABASE=/app/database/database.sqlite
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

To update to a new version:

```bash
# Pull latest changes
git pull

# Rebuild containers
docker compose build

# Apply migrations
docker compose exec app php artisan migrate --force

# Restart services
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
# Run migrations
docker compose exec app php artisan migrate

# Database backup (SQLite)
docker compose exec app cp /app/database/database.sqlite /app/database/backup.sqlite

# Access tinker
docker compose exec app php artisan tinker
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
