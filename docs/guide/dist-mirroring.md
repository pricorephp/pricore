# Dist Archives

For every package version, Pricore stores a zip archive and tells Composer to download that instead of cloning the repository. Installs are faster, don't need Git or access to your Git server, and keep working when the Git provider is down.

This is on by default and needs no setup. This page explains how archives are stored and how to limit the disk space they take.

## How it works

During a sync, Pricore downloads an archive of each new version from GitHub, GitLab or Bitbucket, or creates one with `git archive` for Generic Git repositories. For packages in a [monorepo](/guide/repositories#monorepos), it cuts the package's directory out of the full archive.

The Composer metadata then contains both a `source` and a `dist` for each version:

```json
{
    "name": "acme/billing",
    "version": "1.0.0",
    "source": {
        "type": "git",
        "url": "https://github.com/acme/billing.git",
        "reference": "4f2a9c..."
    },
    "dist": {
        "type": "zip",
        "url": "https://pricore.example.com/acme/dists/acme/billing/1.0.0/4f2a9c....zip",
        "reference": "4f2a9c...",
        "shasum": "da39a3ee5e6b4b0d3255bfef95601890afd80709"
    }
}
```

Composer prefers `dist` and checks the archive against the `shasum` after downloading. Until an archive exists, or when building it fails, the version is offered with `source` only and Composer clones it. Monorepo packages have no usable `source`, so they only appear once their archive is ready.

[Uploaded packages](/guide/packages#uploading-packages) are always served from the uploaded archive, and [registry mirrors](/guide/registry-mirrors) store upstream archives when **Mirror dist archives** is on.

## Storage

Archives are stored on the `local` disk, in `storage/app/private`, unless you set `DIST_DISK=s3`. Use S3 or an S3-compatible service when you run Pricore on more than one server; see [Package storage](/getting-started/configuration#package-storage).

With S3, Pricore redirects Composer to a signed S3 URL, so downloads don't pass through Pricore. The links are valid for `DIST_SIGNED_URL_EXPIRY` minutes, 30 by default.

Set `DIST_ENABLED=false` to stop building archives altogether. Composer then clones every package, and monorepo packages can't be installed.

## Branches and lock files

A tag always points to the same commit, but a branch moves. When `main` gets a new commit, Pricore builds a new `dev-main` archive and keeps the previous one.

That previous archive is still needed. `composer.lock` records the exact commit it resolved, and `composer install` downloads that commit. If the old archive were deleted, every lock file pointing to it would fail to install until someone ran `composer update`.

Archives that a branch has moved past are called detached. They are kept forever unless you set a retention period.

## Cleaning up

Two settings limit how many archives are kept. Both are applied by one command:

```bash
php artisan dist:cleanup
```

The command doesn't run on its own. Schedule it yourself, for example daily with cron:

```
0 3 * * * cd /var/www/pricore && php artisan dist:cleanup
```

With Docker, run it with `docker compose exec app php artisan dist:cleanup`.

### Detached branch archives

```bash
DIST_KEEP_DETACHED_DAYS=30
```

Removes archives that stopped being the head of their branch more than 30 days ago. The age is counted from the moment the branch moved on, not from when the archive was built.

::: warning
A lock file that pins a commit older than this window fails to install with a 404. Run `composer update` for that package, or raise the window.
:::

### Old releases

Each package has a `dist_keep_last_releases` setting. When it is above `0`, only the archives of that many most recent stable releases are kept. Dev and pre-release versions aren't affected, and neither are uploaded packages, whose archive is the only copy.

There is no setting for this in the interface yet. Set it in the `packages` table.

Removing an archive doesn't remove the version. Composer falls back to cloning it from Git, which doesn't work for monorepo packages.

## Troubleshooting

**Versions have no archive.** Check that `DIST_ENABLED` is on, and look for failed jobs in Horizon. Archive failures are logged as warnings and don't fail the sync.

**Disk usage keeps growing.** Every push to a branch adds an archive. Set `DIST_KEEP_DETACHED_DAYS` and schedule `dist:cleanup`.
