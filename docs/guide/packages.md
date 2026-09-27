# Packages

Packages in Pricore represent your private Composer packages. They can be synced automatically from Git repositories, mirrored from another registry, or published by uploading zip archives.

## Package Basics

Each package has:

- **Name** - Follows Composer naming convention: `vendor/package-name`
- **Description** - Brief description of the package
- **Type** - Package type (library, project, metapackage, etc.)
- **Visibility** - Private or public within your organization
- **Versions** - Release versions synced from tags or branches

## Creating Packages

### From a Repository

The recommended way to create packages is by connecting a Git repository:

1. [Connect a repository](/guide/repositories) to your organization
2. Pricore automatically discovers `composer.json` and creates the package, or several packages for a [monorepo](/guide/repositories#monorepos)
3. Tags become release versions, branches become dev versions

### From an Archive Upload

For code without a Git repository, such as a third-party SDK you received as a zip or a package whose `composer.json` is generated in CI, you can upload the archive itself:

1. Go to **Packages** and click **Upload Package**
2. Choose a `.zip` with `composer.json` at its root, or inside a single top-level directory
3. Enter a version, or leave it empty to use the `version` field from `composer.json`

The package name comes from `composer.json` and must be a lowercase `vendor/package` name. Pricore serves the archive exactly as uploaded, so Composer verifies it against the stored checksum.

Only organization owners and admins can create uploaded packages. After that, new versions can be uploaded from the package page, or [from CI](#publishing-from-ci) with a token that can publish.

Uploaded packages follow a few rules:

- **Releases are immutable.** Uploading a different archive for an existing release such as `1.2.0` is rejected; publish `1.2.1` instead. Re-uploading the identical archive is a no-op, so retried CI jobs succeed.
- **Dev versions can be replaced.** A new `dev-main` or `2.x-dev` archive replaces the previous one. The old archive stays available for lock files that still reference it.
- **Sources don't mix.** A package that is synced from a repository or mirror can't receive uploads, and repositories and mirrors never take over an uploaded package.

::: tip
The maximum archive size is 64 MB by default. Self-hosted instances can change it with `ARTIFACT_MAX_SIZE` (in megabytes); PHP's `upload_max_filesize` and `post_max_size`, and any reverse proxy body limit, must allow at least as much.
:::

### Publishing from CI

Once an uploaded package exists, a pipeline can publish new versions to it. Create an [organization token](/guide/tokens) with **Allow publishing packages** enabled, store it as a CI secret, and upload the archive:

```bash
curl --fail-with-body \
  -H "Authorization: Bearer $PRICORE_PUBLISH_TOKEN" \
  -F archive=@build/package.zip \
  -F version=1.4.0 \
  https://pricore.yourcompany.com/your-organization/api/packages/upload
```

The `version` field is optional when `composer.json` in the archive has a `version`. See the [API reference](/api/#upload-a-package-version) for responses and errors.

The API only publishes versions of packages that already exist. This keeps a leaked CI token from introducing a new package, for example one named after a public package, that every project using your registry would then install.

## Package Versions

Versions in Pricore follow Composer's versioning rules:

| Source     | Version Format   | Example                     |
| ---------- | ---------------- | --------------------------- |
| Git tag    | Semantic version | `1.0.0`, `v2.1.3`           |
| Git branch | Dev version      | `dev-main`, `dev-feature-x` |
| Upload     | Given version    | `1.0.0`, `dev-main`         |

### Version Metadata

Each version stores:

- Complete `composer.json` content
- Dependencies and dev-dependencies
- Autoload configuration
- Scripts and extra metadata

### Syncing Versions

Versions are synced automatically when:

- A webhook is triggered by your Git provider
- You manually trigger a sync from the package page
- The scheduled sync job runs (if configured)

## Using Packages with Composer

### 1. Add the Repository

Add your Pricore organization as a Composer repository:

```bash
composer config repositories.your-organization composer https://pricore.yourcompany.com/your-organization
```

### 2. Authenticate

Configure Composer with your access token:

```bash
composer config --global --auth http-basic.pricore.yourcompany.com token YOUR_ACCESS_TOKEN
```

Or add to `auth.json`:

```json
{
    "http-basic": {
        "pricore.yourcompany.com": {
            "username": "token",
            "password": "YOUR_ACCESS_TOKEN"
        }
    }
}
```

### 3. Require the Package

```bash
composer require your-vendor/your-package
```

## Package Visibility

### Private Packages

Private packages are only accessible to:

- Organization members
- Users with valid access tokens that have permission

### Proxied Packages

Pricore can proxy packages from Packagist, allowing you to:

- Cache packages locally for faster installs
- Maintain availability even if Packagist is down
- Control which public packages your team can use

## Package Metadata

### Viewing Package Details

The package page shows:

- **Overview** - Description, stats, and quick links
- **Versions** - All available versions with release dates
- **Dependencies** - Required packages for each version
- **Dependents** - Other packages that depend on this one

### Editing Packages

Package owners and admins can:

- Update description and metadata
- Change visibility settings
- Link/unlink repositories
- Delete the package

## Download Statistics

Pricore automatically tracks download counts per package version via the Composer `notify-batch` protocol. When Composer installs packages from your Pricore registry, it sends download notifications that are recorded for each version. No additional configuration is needed — this works out of the box with Composer 2.

## Best Practices

1. **Follow Composer naming** - Use lowercase, hyphenated names: `acme/my-package`
2. **Use semantic versioning** - Tag releases with proper semver: `1.0.0`, `1.0.1`, `1.1.0`
3. **Keep composer.json complete** - Include description, license, authors, and autoload
4. **Document your packages** - Add README files and use the description field
5. **Audit versions** - Remove outdated or broken versions when necessary
