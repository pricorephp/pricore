# Configuration

Pricore is configured with environment variables, either in `.env` or in the environment of your containers. This page lists the variables specific to Pricore and the Laravel settings you are most likely to change. Everything else follows the [Laravel defaults](https://laravel.com/docs/configuration).

## Application

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_URL` | The public URL of Pricore. Composer URLs, webhook URLs, OAuth callbacks and passkeys are all built from it. With an `https://` URL, all generated URLs use HTTPS, even behind a proxy that terminates TLS. | - |
| `APP_KEY` | Encryption key. Pricore encrypts SSH keys and Git credentials with it, so keep it stable and back it up. | - |
| `APP_ENV` | `production` or `local` | `production` |
| `APP_DEBUG` | Show detailed errors. Keep this off in production. | `false` |
| `SIGN_UP_ENABLED` | Let anyone create an account. When off, only invited users can sign up. | `false` |
| `MIRROR_ON_DEMAND_ENABLED` | Allow [on-demand mirrors](/guide/registry-mirrors#mirroring-packagist), such as a Packagist mirror. When off, they can't be added and existing ones stop importing new packages. | `true` |
| `TRUSTED_PROXIES` | Comma-separated IPs or CIDR ranges of your load balancer or reverse proxy, or `*` to trust any. Needed so Pricore sees the real client IP and scheme. | - |

## Database

SQLite works well for most installations. MySQL 8 and PostgreSQL 14 or newer are also supported.

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

## Redis

Horizon runs the queue on Redis, so Redis is required. Use it for the cache and sessions too:

```bash
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

## Mail

Pricore sends invitations, password resets and vulnerability notifications by email. Any [Laravel mail driver](https://laravel.com/docs/mail) works:

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=pricore@example.com
MAIL_FROM_NAME=Pricore
```

Without working mail, invited users can't receive their invitation link.

## Realtime updates (Reverb)

Pricore uses [Laravel Reverb](https://laravel.com/docs/reverb) to push sync progress and new vulnerabilities to open browser tabs. It is optional: without it, pages update when you reload them.

```bash
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=pricore
REVERB_APP_KEY=a-random-key
REVERB_APP_SECRET=a-random-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

There are two sets of variables, because the Reverb server and the browser usually see different addresses. `REVERB_HOST`, `REVERB_PORT` and `REVERB_SCHEME` are where Reverb itself listens, for example `http` on port 8080 inside your network. `REVERB_ECHO_*` is what the browser connects to, typically your public domain on port 443 through the reverse proxy.

| Variable | Description | Default |
|----------|-------------|---------|
| `BROADCAST_CONNECTION` | `reverb` to enable realtime updates, `log` or `null` to disable them | `null` |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Credentials shared by Pricore and Reverb. Pick any random values. | - |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | Where the Reverb server listens | `localhost` / `8080` / `http` |
| `REVERB_ECHO_HOST` | Host the browser connects to | The page's hostname |
| `REVERB_ECHO_PORT` | Port the browser connects to | `443` |
| `REVERB_ECHO_SCHEME` | Scheme the browser connects with | `https` |

The browser receives these values at runtime, so changing them doesn't require a frontend rebuild.

## Sign in with GitHub or GitLab

GitHub and GitLab OAuth apps serve two purposes: users can sign in with them, and they can connect their account under **Settings** > **Git Providers** so Pricore can list and sync their repositories. Without an OAuth app, users can still add a personal access token as a Git credential.

### GitHub

Create an OAuth App under GitHub **Settings** > **Developer settings** > **OAuth Apps**, with `{APP_URL}/auth/github/callback` as the callback URL.

```bash
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
```

### GitLab

Create an application under [GitLab **User Settings** > **Applications**](https://gitlab.com/-/user_settings/applications), or the same page on your own GitLab instance:

- **Redirect URI:** `{APP_URL}/auth/gitlab/callback`
- **Scopes:** `read_user` for signing in, and `api` for reading repositories and registering webhooks. GitLab has no narrower scope that allows managing webhooks.

```bash
GITLAB_CLIENT_ID=your-application-id
GITLAB_CLIENT_SECRET=your-secret
GITLAB_INSTANCE_URI=https://gitlab.example.com   # only for self-hosted GitLab
```

The callback URLs default to `/auth/{provider}/callback` on `APP_URL`. Set `GITHUB_REDIRECT_URI` or `GITLAB_REDIRECT_URI` only if you need something else.

### Bitbucket

Bitbucket has no OAuth sign-in. Each user adds an API token under **Settings** > **Git Providers**; see [Repositories](/guide/repositories#github-gitlab-and-bitbucket) for the scopes it needs.

## Package storage

Dist archives and uploaded packages are stored on the `local` disk by default, in `storage/app/private`. When you run more than one server, use S3 or an S3-compatible service so every server sees the same files:

```bash
DIST_DISK=s3
```

| Variable | Description | Default |
|----------|-------------|---------|
| `DIST_ENABLED` | Build zip archives for package versions. See [Dist archives](/guide/dist-mirroring). | `true` |
| `DIST_DISK` | `local` or `s3` | `local` |
| `DIST_SIGNED_URL_EXPIRY` | Lifetime in minutes of the S3 download links Composer is redirected to | `30` |
| `DIST_KEEP_DETACHED_DAYS` | Days to keep archives of commits a branch has moved past. Unset keeps them forever. | - |
| `ARTIFACT_MAX_SIZE` | Maximum size of an uploaded package archive, in megabytes | `64` |
| `ARTIFACT_UPLOAD_RATE_LIMIT` | Uploads per minute per token | `30` |

When you raise `ARTIFACT_MAX_SIZE`, also raise PHP's `upload_max_filesize` and `post_max_size`, and the body size limit of your reverse proxy (`client_max_body_size` in nginx). The Docker image allows 64 MB.

### S3 credentials

For AWS, the standard variables are enough:

```bash
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=pricore
```

For an S3-compatible service such as Cloudflare R2, MinIO or DigitalOcean Spaces, use the `S3_*` variables. Each one overrides its `AWS_*` counterpart, so you can store packages elsewhere while other AWS integrations keep their own credentials:

```bash
S3_ACCESS_KEY_ID=your-key
S3_SECRET_ACCESS_KEY=your-secret
S3_DEFAULT_REGION=auto
S3_BUCKET=pricore
S3_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
S3_USE_PATH_STYLE_ENDPOINT=true
```

`S3_URL` overrides `AWS_URL` in the same way.

## Private networks

Organization admins can enter any URL as a Generic Git repository or a registry mirror. To stop them from reaching internal services, Pricore refuses URLs that resolve to private, loopback, link-local or other reserved IP addresses, including through redirects. If your Git server or Satis instance is on a private network, allow its exact hostname or IP:

| Variable | Description |
|----------|-------------|
| `GIT_ALLOWED_PRIVATE_HOSTS` | Hosts allowed for [Generic Git repositories](/guide/repositories#git-servers-on-a-private-network) |
| `MIRROR_ALLOWED_PRIVATE_HOSTS` | Hosts allowed for [registry mirrors](/guide/registry-mirrors#private-networks) |

Both take a comma-separated list such as `git.internal,10.20.30.40`. Wildcards and CIDR ranges are not accepted. GitHub, GitLab and Bitbucket repositories are not affected.

Pricore pins the IP address it validated when it connects. Mirror requests therefore ignore `HTTP_PROXY` and connect directly. Git does use a configured proxy, but then the proxy resolves the hostname and the pinning doesn't apply.

## MCP server

Pricore serves an [MCP server](/guide/mcp) at `/mcp`. Clients register themselves and sign in with OAuth, with Pricore as the authorization server.

| Variable | Description | Default |
|----------|-------------|---------|
| `MCP_ENABLED` | Serve `/mcp` and the OAuth endpoints (`/oauth/*`, `/.well-known/oauth-*`) | `true` |
| `MCP_REDIRECT_DOMAINS` | Comma-separated prefixes such as `https://claude.ai` that clients may redirect to after sign-in. `*` allows any; the user still approves every client. When you restrict it, include `http://localhost` for CLI clients such as Claude Code. `claude://`, `cursor://` and `vscode://` are always allowed. | `*` |
| `MCP_RATE_LIMIT` | Requests per minute per user | `120` |
| `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | PEM keys that sign OAuth tokens | `storage/oauth-private.key` / `storage/oauth-public.key` |

Generate the key files with `php artisan passport:keys`; the Docker image does this on first boot. All servers behind a load balancer need the same keys.

Clients discover the OAuth endpoints from `APP_URL`, so it must be the public URL, and `TRUSTED_PROXIES` must be set when Pricore runs behind a proxy.

## Horizon

[Horizon](https://laravel.com/docs/horizon) runs the background jobs: repository syncs, archive builds, mirror imports and advisory scans. Its dashboard is at `/horizon`.

| Variable | Description | Default |
|----------|-------------|---------|
| `HORIZON_ALLOWED_EMAILS` | Comma-separated emails of users who may open the dashboard outside the `local` environment | - |
| `HORIZON_PATH` | Dashboard path | `horizon` |
| `HORIZON_DOMAIN` | Serve the dashboard on a separate domain | - |

## Sentry

Set a DSN to report errors to [Sentry](https://sentry.io/):

```bash
SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
SENTRY_TRACES_SAMPLE_RATE=0.1
```

`SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`, `SENTRY_SAMPLE_RATE` and `SENTRY_PROFILES_SAMPLE_RATE` work as described in the [Sentry Laravel docs](https://docs.sentry.io/platforms/php/guides/laravel/).

## Example

A production `.env` for a single server with PostgreSQL, behind a reverse proxy:

```bash
APP_URL=https://pricore.example.com
APP_KEY=base64:...
APP_ENV=production
APP_DEBUG=false
TRUSTED_PROXIES=127.0.0.1

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=pricore
DB_USERNAME=pricore
DB_PASSWORD=...

REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=pricore
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=pricore@example.com

GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...

HORIZON_ALLOWED_EMAILS=you@example.com
```
