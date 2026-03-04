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
| **Local Git** | Supported | Uses `git archive` command |
| **Generic Git** | Not supported | Falls back to source-only |

When a provider does not support archive downloads, the sync continues normally — packages are served with `source` references only.

## Archive Retention

By default, Pricore keeps dist archives for all versions. You can configure per-package retention to automatically clean up old archives and save disk space.

The `dist_keep_last_releases` setting on each package controls how many stable release archives to keep. When set to a value greater than `0`, the `dist:cleanup` command removes archives for older stable versions while keeping the most recent ones.

For example, if `dist_keep_last_releases` is set to `5`, only the 5 most recent stable version archives are kept. Dev versions and pre-release versions are not affected.

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
- Schedule `php artisan dist:cleanup` to run daily
- Consider using S3 storage for large registries
