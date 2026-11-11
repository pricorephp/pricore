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
| `DIST_KEEP_DOWNLOADED_DAYS` | Days retention keeps an archive after its last download (`0` turns it off) | `90` |

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
| **Generic Git** | Supported | Uses `git archive` on the local clone |

A version is published, or moved to a new commit, only once its archive is stored. Otherwise a `composer.lock` resolved in between would pin the commit without a `dist` entry, and installs would need access to the Git repository. When an archive fails to build:

- a new version is kept out of the Composer metadata;
- a version already published stays on its previous commit and archive;
- the failure is listed in the sync log, and the next sync retries it.

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
Leaving this unset keeps every detached archive indefinitely. Setting it trades disk space for a rebuild on the next download of a pruned commit (see [Rebuilding removed archives](#rebuilding-removed-archives)).
:::

### Archives still in use

Both settings above skip archives downloaded within the last `DIST_KEEP_DOWNLOADED_DAYS` days, so lock files still installing them keep working:

```bash
DIST_KEEP_DOWNLOADED_DAYS=90
```

Downloads are recorded at most once a day per archive. Installs served from Composer's local cache never reach Pricore, so they do not count.

### Rebuilding removed archives

For packages synced from a repository, retention removes only the archive file. The version keeps offering its `dist`, and the first download of a removed archive rebuilds it from the repository:

1. Pricore answers `503 Service Unavailable` with `Retry-After: 60` and queues the rebuild.
2. Composer waits and retries, up to three times, then downloads the rebuilt archive.

If the rebuild fails, for example because the commit is no longer reachable, downloads answer `404` for 10 minutes and Composer falls back to `source`. The next download after that tries again.

Lock files store the archive's SHA-1 checksum. When a rebuilt archive differs from the original, Pricore keeps the new one and logs a warning. Lock files resolved before then fail the checksum and fall back to `source`; run `composer update` on the package to refresh them.

Mirrored and uploaded packages cannot be rebuilt: retention removes their archives for good.

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
- Open the sync log on the repository page: archives that failed to build are listed with the provider's error

### Disk space growing

- Configure `dist_keep_last_releases` on packages with many releases
- Set `DIST_KEEP_DETACHED_DAYS` to bound archives from moved branches
- Schedule `php artisan dist:cleanup` to run daily — it is not scheduled by default
- Consider using S3 storage for large registries

### `composer install` fails with 404 on a removed archive

The archive was removed by retention and could not be rebuilt, or the package is
mirrored. Check the logs for `Failed to rebuild dist archive`, or run
`composer update` to move the lock to an archive that is still stored.
