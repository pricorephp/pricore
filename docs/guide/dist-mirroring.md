# Distribution Mirroring

Distribution mirroring lets Composer download zip archives directly from Pricore instead of cloning full Git repositories. This results in significantly faster installs and removes the need for Git on the client machine.

## How It Works

When a repository is synced, Pricore downloads a zip archive of each version from your Git provider and stores it locally (or on S3). Composer metadata is then served with `dist` URLs pointing to these archives.

Without dist mirroring, Composer must clone the full Git repository and check out the correct ref for every package install. With dist mirroring enabled, Composer downloads a small zip file instead — the same way it works with Packagist.

## Enabling Dist Mirroring

Dist mirroring is enabled by default. You can control it with the `DIST_ENABLED` environment variable:

```bash
DIST_ENABLED=true
```

When disabled, Pricore serves only `source` references and Composer falls back to Git cloning.

## Configuration

| Variable | Description | Default |
|----------|-------------|---------|
| `DIST_ENABLED` | Enable or disable dist archive creation | `true` |
| `DIST_DISK` | Storage disk for archives (`local` or `s3`) | `local` |
| `DIST_SIGNED_URL_EXPIRY` | Signed URL expiry in minutes (S3 only) | `30` |
| `DIST_KEEP_DETACHED_DAYS` | Days to keep archives a branch has moved past | unset (keep forever) |

### Local Storage

By default, dist archives are stored on the `local` disk under `storage/app/private`. No additional configuration is needed.

### S3 Storage

For production deployments, you can store archives on S3 (or any S3-compatible service):

```bash
DIST_DISK=s3

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

When using S3, Pricore redirects Composer to a signed temporary URL for each download. This keeps archive traffic off your application server. The signed URL expiry can be configured with `DIST_SIGNED_URL_EXPIRY`.

## Provider Support

Dist archive creation depends on your Git provider's capabilities:

| Provider | Support | Method |
|----------|---------|--------|
| **GitHub** | Supported | Downloads zipball via GitHub API |
| **GitLab** | Supported | Downloads archive via GitLab API |
| **Bitbucket** | Supported | Downloads zip archive via Bitbucket API |
| **Local Git** | Supported | Uses `git archive` command |
| **Generic Git** | Not supported | Falls back to source-only |

When a provider does not support archive downloads, the sync continues normally — packages are served with `source` references only.

## Branch Versions and Lock Files

Tags are immutable, but branches move. When a branch like `main` receives a new commit, Pricore builds a fresh archive for it — and keeps the previous one.

This matters for `composer install`. A `composer.lock` pins the exact commit that was resolved, and `install` downloads that commit specifically rather than re-reading metadata. If the older archive were discarded when the branch moved, every lock file pinning it would fail to install until someone ran `composer update`.

So each archive is recorded against its own commit. When a branch moves on, the previous archive is marked **detached**: no longer what the branch resolves to, but still downloadable by the commit that named it.

## Archive Retention

By default, Pricore keeps dist archives for all versions, including detached ones. Two settings control cleanup, both applied by the `dist:cleanup` command.

### Stable releases

The `dist_keep_last_releases` setting on each package controls how many stable release archives to keep. When set to a value greater than `0`, `dist:cleanup` removes archives for older stable versions while keeping the most recent ones.

For example, if `dist_keep_last_releases` is set to `5`, only the 5 most recent stable version archives are kept. Dev versions and pre-release versions are not affected.

### Detached branch archives

`DIST_KEEP_DETACHED_DAYS` bounds how long archives are kept after their branch moves past them:

```bash
DIST_KEEP_DETACHED_DAYS=30
```

The window is measured from when an archive **stopped being current**, not from when it was built — a long-lived branch archive superseded yesterday is kept for the full window regardless of its age.

::: warning
Leaving this unset keeps every detached archive indefinitely, which is the safe default: any commit ever synced stays installable. Setting it trades that guarantee for disk space. Lock files pinning a commit older than the window will fail to install.
:::

### Running Cleanup

Run the cleanup command manually:

```bash
php artisan dist:cleanup
```

Or schedule it in your application's console kernel to run periodically.

::: info
Cleanup only removes the zip archive from disk. The package version itself remains available — Composer will fall back to Git cloning for versions without a dist archive.
:::

## How Composer Uses Dist

When Composer resolves a package from Pricore, the metadata includes both `source` and `dist` entries:

```json
{
    "name": "acme/my-package",
    "version": "1.0.0",
    "source": {
        "type": "git",
        "url": "https://github.com/acme/my-package.git",
        "reference": "abc123..."
    },
    "dist": {
        "type": "zip",
        "url": "https://pricore.yourcompany.com/org/acme/dists/acme/my-package/1.0.0/abc123....zip",
        "reference": "abc123...",
        "shasum": "da39a3ee5e6b4b0d3255bfef95601890afd80709"
    }
}
```

Composer prefers `dist` over `source` by default, so installs automatically use the zip archive when available. The `shasum` field provides integrity verification — Composer checks the SHA-1 hash after downloading to ensure the archive hasn't been tampered with.

## Troubleshooting

### Archives not being created

- Verify `DIST_ENABLED=true` in your `.env`
- Check that your Git provider supports archive downloads (see [Provider Support](#provider-support))
- Review your application logs for download errors — dist failures are logged as warnings and do not fail the sync

### Disk space growing

- Configure `dist_keep_last_releases` on packages with many releases
- Set `DIST_KEEP_DETACHED_DAYS` to bound archives from moved branches
- Schedule `php artisan dist:cleanup` to run daily — it is not scheduled by default
- Consider using S3 storage for large registries

### `composer install` fails with 404 on a branch version

The lock file pins a commit whose archive is no longer stored. This happens when
`DIST_KEEP_DETACHED_DAYS` is set and the commit fell outside the window. Either
raise the window, or run `composer update` to move the lock to the current head.
