# Packages

Packages in Pricore represent your private Composer packages. They can be synced automatically from Git repositories or managed manually.

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
2. Pricore automatically discovers `composer.json` and creates the package
3. Tags become release versions, branches become dev versions

### Manual Creation

For packages not hosted in Git:

1. Navigate to **Packages** > **Create Package**
2. Enter the package name and metadata
3. Upload or configure versions manually

## Package Versions

Versions in Pricore follow Composer's versioning rules:

| Source | Version Format | Example |
|--------|---------------|---------|
| Git tag | Semantic version | `1.0.0`, `v2.1.3` |
| Git branch | Dev version | `dev-main`, `dev-feature-x` |

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

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://pricore.yourcompany.com/org/your-organization"
        }
    ]
}
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
