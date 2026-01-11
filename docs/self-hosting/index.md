# Self-Hosting Overview

Pricore is designed for self-hosting, giving you complete control over your private package registry.

## Architecture

Pricore consists of several components:

```
┌─────────────────────────────────────────────────────────┐
│                    Load Balancer                        │
│                   (nginx/Caddy)                         │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                    FrankenPHP                           │
│              (Web Server + PHP Runtime)                 │
│                                                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐  │
│  │   Web UI    │  │  Composer   │  │     Webhook     │  │
│  │  (Inertia)  │  │     API     │  │    Endpoints    │  │
│  └─────────────┘  └─────────────┘  └─────────────────┘  │
└─────────────────────────────────────────────────────────┘
         │                  │                  │
         ▼                  ▼                  ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────────┐
│  Database   │    │    Redis    │    │   Git Providers │
│  (SQLite/   │    │  (Queues,   │    │  (GitHub/GitLab │
│   MySQL/    │    │   Cache,    │    │   /Bitbucket)   │
│   Postgres) │    │  Sessions)  │    │                 │
└─────────────┘    └─────────────┘    └─────────────────┘
```

## Components

### FrankenPHP

Pricore uses [FrankenPHP](https://frankenphp.dev) as its application server:

- Built on Caddy with native PHP support
- HTTP/3 and automatic HTTPS
- High-performance worker mode
- Simple single-binary deployment

### Laravel Horizon

Background job processing is handled by [Laravel Horizon](https://laravel.com/docs/horizon):

- Queue worker management
- Real-time monitoring dashboard
- Job retry and failure handling
- Automatic scaling based on queue load

### Redis

Redis is used for:

- **Queues** - Background job processing
- **Cache** - Application caching layer
- **Sessions** - User session storage

## Deployment Options

### Docker (Recommended)

The easiest way to deploy Pricore. See the [Docker guide](/self-hosting/docker).

### Manual Deployment

For custom setups or environments without Docker:

1. Install [requirements](/self-hosting/requirements)
2. Clone the repository
3. Install dependencies
4. Configure environment
5. Set up a web server (nginx, Apache, or FrankenPHP)
6. Configure process manager (systemd, supervisor)

## Scaling

### Horizontal Scaling

Pricore can be scaled horizontally:

1. Run multiple web containers behind a load balancer
2. Use a shared database (MySQL/PostgreSQL)
3. Use Redis cluster for caching/sessions
4. Configure sticky sessions if needed

### Vertical Scaling

For smaller deployments, vertical scaling may be sufficient:

- Increase container resources
- Optimize PHP-FPM workers
- Tune database connections
- Adjust Horizon queue workers

## Monitoring

### Health Checks

Pricore exposes a health endpoint:

```
GET /health
```

Returns `200 OK` when the application is healthy.

### Horizon Dashboard

Access the Horizon dashboard at `/horizon` to monitor:

- Queue throughput
- Job processing times
- Failed jobs
- Worker status

### Logs

Application logs are written to:

- `storage/logs/laravel.log` - Application logs
- `storage/logs/horizon.log` - Queue worker logs

## Backup Strategy

### Database

Regular backups of your database are essential:

```bash
# SQLite
cp database/database.sqlite backup/

# MySQL
mysqldump -u user -p pricore > backup/pricore.sql

# PostgreSQL
pg_dump -U user pricore > backup/pricore.sql
```

### Storage

Back up the `storage` directory which contains:

- Application cache
- Log files
- Any uploaded files

### Redis

If using Redis persistence:

```bash
redis-cli BGSAVE
cp /var/lib/redis/dump.rdb backup/
```

## Security Considerations

### Network Security

- Run behind a reverse proxy with TLS termination
- Restrict database access to application servers
- Use firewall rules to limit exposed ports

### Application Security

- Keep dependencies updated
- Use strong `APP_KEY`
- Disable debug mode in production
- Configure secure session settings

### Access Control

- Use strong passwords
- Enable two-factor authentication
- Regularly audit access tokens
- Review organization memberships

## Next Steps

- [Docker Deployment](/self-hosting/docker) - Deploy with Docker Compose
- [Requirements](/self-hosting/requirements) - System requirements for manual deployment
