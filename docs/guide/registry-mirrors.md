# Registry Mirrors

Registry mirrors let you import packages from external Composer registries into your Pricore organization. This is useful when you want to centralize packages from multiple sources — such as internal Satis repositories or third-party registries — so all organization members can install them from a single Composer endpoint.

## How It Works

A registry mirror connects to an external Composer registry, fetches all available packages and their version metadata, and imports them into your organization. Mirrored packages are served alongside your repository-synced packages through the same Composer API.

When dist mirroring is enabled, Pricore also downloads the zip archives from the upstream registry and stores them locally. This means your team members don't need credentials to the original registry — they install everything through Pricore.

Mirrors are synced automatically every 4 hours. You can also trigger a sync manually at any time.

## Adding a Mirror

1. Navigate to **Organization Settings** > **Registry Mirrors**
2. Click **Add Mirror**
3. Fill in the details:
   - **Name** — A label for the mirror (e.g., "Internal Satis")
   - **Registry URL** — The base URL of the external registry (must serve a `packages.json` endpoint)
   - **Authentication** — Choose the appropriate method:
     - **None** — For public registries
     - **HTTP Basic** — Username and password
     - **Bearer Token** — Token-based authentication
   - **Mirror dist archives** — When enabled, Pricore downloads and stores zip archives locally (recommended)
4. Click **Add Mirror**

Pricore immediately starts syncing packages from the registry.

## Supported Registry Formats

Pricore supports registries that serve a standard Composer `packages.json` file:

| Format | Description | Support |
|--------|-------------|---------|
| **Inline** | All package data directly in `packages.json` | Supported |
| **Includes** | Package data split into referenced include files | Supported |

These formats cover most Satis repositories and Composer-compatible registries.

## Sync Behavior

- **Automatic sync** — All mirrors are synced every 4 hours
- **Manual sync** — Click the **Sync** button on any mirror to trigger an immediate sync
- **Incremental** — Only new or changed versions are processed; unchanged versions are skipped
- **Stale removal** — Versions that no longer exist in the upstream registry are automatically removed

Each version is processed as an independent job, so syncs are parallelized across your queue workers.

## Dist Mirroring

When **Mirror dist archives** is enabled on a mirror, Pricore downloads zip archives for each package version from the upstream registry and stores them on your configured dist disk (local or S3).

This requires:
- `DIST_ENABLED=true` in your environment (enabled by default)
- The upstream registry credentials must have permission to download dist archives

If dist downloads fail (e.g., due to a 403 error), the error is displayed on the mirror card in the settings page.

::: tip
For multi-node deployments (e.g., Kubernetes), set `DIST_DISK=s3` so dist archives are accessible from all nodes. See [Distribution Mirroring](/guide/dist-mirroring) for storage configuration.
:::

## CLI

You can also sync mirrors from the command line:

```bash
# Sync a specific mirror by UUID
php artisan sync:mirror {uuid}

# Sync all mirrors for an organization
php artisan sync:mirror --organization={slug-or-uuid}

# Sync all mirrors
php artisan sync:mirror --all
```

## Mirrored Packages

Packages imported from a mirror appear in your organization's package list like any other package. On the package detail page, a **Mirror** label indicates which mirror the package was imported from, linking back to the mirrors settings page.

Mirrored packages are fully compatible with the Composer API — your team installs them the same way as repository-synced packages, using your organization's Composer endpoint and access token.
