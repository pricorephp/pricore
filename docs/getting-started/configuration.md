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
```

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

## Security Settings

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
```
