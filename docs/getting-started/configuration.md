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
| `APP_URL` | Public URL of your Pricore instance. When set to an `https://` URL, all generated URLs are forced to HTTPS — important when running behind a reverse proxy that terminates SSL. | - |

### Registration

```bash
SIGN_UP_ENABLED=false
```

| Variable | Description | Default |
|----------|-------------|---------|
| `SIGN_UP_ENABLED` | Allow public registration without an invitation | `false` |

By default, registration is invite-only. Users can only create accounts after receiving an organization invitation. Set `SIGN_UP_ENABLED=true` to allow anyone to register.

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

### Realtime Updates (Reverb)

Pricore uses [Laravel Reverb](https://laravel.com/docs/reverb) as a WebSocket server to push realtime sync status updates to the browser. When a repository sync starts, completes, or fails, all connected users see the status change immediately.

```bash
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

The Reverb config is automatically injected into the frontend at runtime via meta tags — no Vite build-time variables are needed.

The `REVERB_*` variables configure the **server-side** Reverb process (what it binds to). The `REVERB_ECHO_*` variables configure the **client-side** WebSocket connection (what the browser connects to). When deployed behind a reverse proxy or gateway that terminates TLS, these will differ — Reverb binds to an internal port (e.g., 8080 over HTTP), while the browser connects to the external port (e.g., 443 over HTTPS).

| Variable | Description | Default |
|----------|-------------|---------|
| `BROADCAST_CONNECTION` | Broadcasting driver (`reverb`, `log`, `null`) | `null` |
| `REVERB_APP_ID` | Reverb application ID | - |
| `REVERB_APP_KEY` | Reverb application key | - |
| `REVERB_APP_SECRET` | Reverb application secret | - |
| `REVERB_HOST` | Reverb server hostname | `localhost` |
| `REVERB_PORT` | Reverb server port (internal) | `8080` |
| `REVERB_SCHEME` | Reverb connection scheme (internal) | `http` |
| `REVERB_ECHO_HOST` | Client-facing WebSocket hostname | `window.location.hostname` |
| `REVERB_ECHO_PORT` | Client-facing WebSocket port | `443` |
| `REVERB_ECHO_SCHEME` | Client-facing WebSocket scheme | `https` |

> **Note:** Realtime updates are optional. Without Reverb configured, the application works normally — pages update on navigation or manual refresh.

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
GITLAB_REDIRECT_URI=${APP_URL}/auth/gitlab/callback
GITLAB_INSTANCE_URI=https://gitlab.com  # or your self-hosted GitLab URL
```

| Variable | Description | Default |
|----------|-------------|---------|
| `GITLAB_CLIENT_ID` | GitLab OAuth Application ID | - |
| `GITLAB_CLIENT_SECRET` | GitLab OAuth Application secret | - |
| `GITLAB_REDIRECT_URI` | OAuth callback URL | `{APP_URL}/auth/gitlab/callback` |
| `GITLAB_INSTANCE_URI` | GitLab instance URL (for self-hosted) | `https://gitlab.com` |

Create a GitLab OAuth Application:

**GitLab.com:**
1. Go to [GitLab User Settings > Applications](https://gitlab.com/-/user_settings/applications)
2. Click "Add new application"
3. Set the **Redirect URI** to `{APP_URL}/auth/gitlab/callback`
4. Select scopes: `read_user` and `api`
   - `read_user` — used for login/sign-up (reading user profile and email)
   - `api` — used when connecting GitLab as a git credential (reading repositories, file content, and managing webhooks). GitLab does not offer a narrower scope for webhook management.
5. Click "Save application" and copy the Application ID and Secret

**Self-hosted GitLab:**
1. Go to your GitLab instance > User Settings > Applications
2. Follow the same steps as above
3. Set `GITLAB_INSTANCE_URI` to your instance URL (e.g., `https://gitlab.example.com/`)

### Bitbucket

Bitbucket uses API token authentication instead of OAuth — no environment variables are needed. Credentials are configured per user in **Settings** > **Git Providers**.

1. Go to [Atlassian Account Settings > API tokens](https://id.atlassian.com/manage-profile/security/api-tokens)
2. Click **Create API token with scopes**
3. Select **Bitbucket** as the app and enable the following scopes:
   - `read:user:bitbucket`
   - `read:repository:bitbucket`
   - `read:workspace:bitbucket`
   - `read:webhook:bitbucket`
   - `write:webhook:bitbucket`
4. In Pricore, go to **Settings** > **Git Providers** > **Add Bitbucket**
5. Enter your Atlassian account email and the API token

## Storage Configuration

By default, Pricore stores package distribution files on the local filesystem. To use S3 or an S3-compatible storage provider (like MinIO, DigitalOcean Spaces, or Cloudflare R2), set the filesystem disk to `s3`:

```bash
FILESYSTEM_DISK=s3
```

### AWS S3

If you only use AWS, configure the standard `AWS_*` variables:

```bash
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

### S3-Compatible Storage

If you want to use an S3-compatible provider for storage while keeping the `AWS_*` variables for other AWS services, you can set `S3_*` variables that take precedence over their `AWS_*` counterparts:

```bash
# S3-compatible provider for filesystem storage
S3_ACCESS_KEY_ID=your-s3-compatible-key
S3_SECRET_ACCESS_KEY=your-s3-compatible-secret
S3_DEFAULT_REGION=us-east-1
S3_BUCKET=your-bucket
S3_ENDPOINT=https://s3.example.com
S3_URL=https://your-bucket.s3.example.com
S3_USE_PATH_STYLE_ENDPOINT=true

# AWS credentials (used by other AWS services, and as fallback for S3)
AWS_ACCESS_KEY_ID=your-aws-key
AWS_SECRET_ACCESS_KEY=your-aws-secret
AWS_DEFAULT_REGION=us-east-1
```

| Variable | Description | Fallback |
|----------|-------------|----------|
| `S3_ACCESS_KEY_ID` | Access key for S3-compatible storage | `AWS_ACCESS_KEY_ID` |
| `S3_SECRET_ACCESS_KEY` | Secret key for S3-compatible storage | `AWS_SECRET_ACCESS_KEY` |
| `S3_DEFAULT_REGION` | Storage region | `AWS_DEFAULT_REGION` |
| `S3_BUCKET` | Bucket name | `AWS_BUCKET` |
| `S3_URL` | Custom URL for the storage service | `AWS_URL` |
| `S3_ENDPOINT` | Custom endpoint URL | `AWS_ENDPOINT` |
| `S3_USE_PATH_STYLE_ENDPOINT` | Use path-style endpoints (required by most S3-compatible providers) | `AWS_USE_PATH_STYLE_ENDPOINT` |

If no `S3_*` variables are set, the configuration falls back to the `AWS_*` variables — so existing setups continue to work without changes.

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

SIGN_UP_ENABLED=false

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
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=pricore.yourcompany.com
REVERB_PORT=8080
REVERB_SCHEME=https

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

GITLAB_CLIENT_ID=your-gitlab-client-id
GITLAB_CLIENT_SECRET=your-gitlab-client-secret
# GITLAB_INSTANCE_URI=https://gitlab.example.com  # uncomment for self-hosted

HORIZON_ALLOWED_EMAILS=admin@yourcompany.com

SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0

TRUSTED_PROXIES=
```
