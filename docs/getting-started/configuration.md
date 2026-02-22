# Configuration

Pricore is configured through environment variables in your `.env` file.

## Essential Configuration

### Application Settings

```bash
APP_NAME=Pricore
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pricore.yourcompany.com
```

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name displayed in UI | `Pricore` |
| `APP_ENV` | Environment (`local`, `production`) | `production` |
| `APP_DEBUG` | Enable debug mode (disable in production) | `false` |
| `APP_URL` | Public URL of your Pricore instance | - |

### Database Configuration

Pricore supports SQLite, MySQL, and PostgreSQL:

::: code-group

```bash [SQLite]
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite
```

```bash [MySQL]
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pricore
DB_USERNAME=pricore
DB_PASSWORD=secret
```

```bash [PostgreSQL]
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pricore
DB_USERNAME=pricore
DB_PASSWORD=secret
```

:::

### Redis Configuration

Redis is required for queues, caching, and sessions:

```bash
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

## Git Provider Configuration

To sync packages from Git repositories, configure your provider credentials.

### GitHub

```bash
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
GITHUB_REDIRECT_URI=${APP_URL}/auth/github/callback
```

| Variable | Description | Default |
|----------|-------------|---------|
| `GITHUB_CLIENT_ID` | GitHub OAuth App client ID | - |
| `GITHUB_CLIENT_SECRET` | GitHub OAuth App client secret | - |
| `GITHUB_REDIRECT_URI` | OAuth callback URL | `{APP_URL}/auth/github/callback` |

Create a GitHub OAuth App:
1. Go to GitHub Settings > Developer settings > OAuth Apps
2. Click "New OAuth App"
3. Set the callback URL to `{APP_URL}/auth/github/callback`

### GitLab

```bash
GITLAB_CLIENT_ID=your-gitlab-client-id
GITLAB_CLIENT_SECRET=your-gitlab-client-secret
GITLAB_URL=https://gitlab.com  # or your self-hosted GitLab URL
```

### Bitbucket

```bash
BITBUCKET_CLIENT_ID=your-bitbucket-client-id
BITBUCKET_CLIENT_SECRET=your-bitbucket-client-secret
```

## Mail Configuration

Configure mail for password resets and notifications:

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourcompany.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Horizon (Queue Dashboard)

Pricore uses [Laravel Horizon](https://laravel.com/docs/horizon) to manage queue workers. The following variables configure the Horizon dashboard:

```bash
HORIZON_ALLOWED_EMAILS=admin@yourcompany.com,ops@yourcompany.com
HORIZON_PATH=horizon
```

| Variable | Description | Default |
|----------|-------------|---------|
| `HORIZON_ALLOWED_EMAILS` | Comma-separated list of emails allowed to access the Horizon dashboard (in non-local environments) | `''` (empty) |
| `HORIZON_NAME` | Name displayed in the Horizon UI and notifications | - |
| `HORIZON_DOMAIN` | Subdomain to serve Horizon from (e.g., `horizon.yourcompany.com`) | `null` (same domain) |
| `HORIZON_PATH` | URI path for the Horizon dashboard | `horizon` |
| `HORIZON_PREFIX` | Redis key prefix for Horizon data | `{app_name}_horizon:` |

## Slack Notifications

To send notifications to Slack, configure a bot token:

```bash
SLACK_BOT_USER_OAUTH_TOKEN=xoxb-your-bot-token
SLACK_BOT_USER_DEFAULT_CHANNEL=#packages
```

| Variable | Description | Default |
|----------|-------------|---------|
| `SLACK_BOT_USER_OAUTH_TOKEN` | Slack bot user OAuth token | - |
| `SLACK_BOT_USER_DEFAULT_CHANNEL` | Default Slack channel for notifications | - |

## Error Tracking (Sentry)

Pricore supports [Sentry](https://sentry.io/) for error tracking and performance monitoring:

```bash
SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.2
```

| Variable | Description | Default |
|----------|-------------|---------|
| `SENTRY_LARAVEL_DSN` | Sentry DSN URL (falls back to `SENTRY_DSN`) | - |
| `SENTRY_RELEASE` | Release version tag sent to Sentry | - |
| `SENTRY_ENVIRONMENT` | Environment name in Sentry | `APP_ENV` value |
| `SENTRY_SAMPLE_RATE` | Error event sample rate (`0.0` to `1.0`) | `1.0` |
| `SENTRY_TRACES_SAMPLE_RATE` | Performance traces sample rate (`0.0` to `1.0`) | `null` (disabled) |
| `SENTRY_PROFILES_SAMPLE_RATE` | Profiling sample rate (`0.0` to `1.0`) | `null` (disabled) |
| `SENTRY_ENABLE_LOGS` | Send logs to Sentry | `false` |
| `SENTRY_LOG_LEVEL` | Minimum log level sent to Sentry | `debug` |
| `SENTRY_SEND_DEFAULT_PII` | Include personally identifiable information | `false` |

## Security Settings

### Trusted Proxies

When running behind a load balancer or reverse proxy, configure trusted proxies:

```bash
TRUSTED_PROXIES=192.168.1.0/24,10.0.0.0/8
```

| Variable | Description | Default |
|----------|-------------|---------|
| `TRUSTED_PROXIES` | Comma-separated list of trusted proxy IP addresses or CIDR ranges. Use `*` to trust all proxies. | `''` (empty) |

### Two-Factor Authentication

Two-factor authentication is enabled by default. Users can enable it in their account settings.

### Session Security

```bash
SESSION_LIFETIME=120  # minutes
SESSION_SECURE_COOKIE=true  # require HTTPS
```

## Performance Tuning

### Queue Workers

For production, use Laravel Horizon to manage queue workers:

```bash
# Horizon is included and configured by default
php artisan horizon
```

### Caching

Enable config and route caching for production:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Complete Example

Here's a complete production `.env` example:

```bash
APP_NAME=Pricore
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://pricore.yourcompany.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pricore
DB_USERNAME=pricore
DB_PASSWORD=secure-password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@yourcompany.com
MAIL_PASSWORD=your-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=packages@yourcompany.com
MAIL_FROM_NAME="${APP_NAME}"

GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret

HORIZON_ALLOWED_EMAILS=admin@yourcompany.com

SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0

TRUSTED_PROXIES=
```
