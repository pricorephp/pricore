# Security Auditing

Pricore can automatically check your hosted packages for known security vulnerabilities by syncing advisories from the [Packagist Security Advisories API](https://packagist.org/apidoc#list-security-advisories). Affected versions are flagged in the UI, and `composer audit` works natively against your Pricore registry.

## How It Works

1. **Advisory sync** — Pricore periodically fetches security advisories from Packagist. Advisories are stored at the application level (shared across all organizations) and include the affected package name, version constraints, CVE identifiers, severity, and links to disclosures.

2. **Version matching** — When advisories are synced or new package versions are added, Pricore matches advisories against your hosted packages using [Composer semver](https://github.com/composer/semver) constraint checking. Two types of matches are detected:
   - **Direct** — The package itself is listed in an advisory
   - **Dependency** — A package in `require` or `require-dev` of your package's `composer.json` is listed in an advisory

3. **Notifications** — Organization admins (owners and admins) receive an email when new vulnerabilities are detected in their packages.

## Security Overview Page

Each organization has a **Security** page accessible from the sidebar. It shows:

- Vulnerability counts by severity (critical, high, medium, low)
- A list of affected packages with severity breakdowns
- The latest version and dev versions checked for each package
- When advisories were last synced

Only the **latest stable version** and **dev versions** (active branches) of each package are included in the overview. Old stable releases are excluded to keep the page focused on what's currently relevant. You can still see per-version vulnerability badges on individual package detail pages.

### Severity Filtering

Use the severity dropdown to filter the overview to a specific severity level — for example, to focus on critical and high issues first.

## Package Version Indicators

On the package detail page, each version in the version list shows a vulnerability badge when advisories match that version. The badge color reflects the highest severity:

- **Red** — Critical or high severity
- **Amber** — Medium severity
- **Blue** — Low severity

Clicking a version opens the detail panel, which includes a **Security Advisories** section listing each matched advisory with its title, CVE, severity, and a link to the disclosure. Dependency matches show which dependency triggered the match.

## Composer Audit

Pricore implements the Packagist-compatible `security-advisories` endpoint, so `composer audit` works natively against your registry.

To use it, configure Composer to use your Pricore registry:

```bash
composer config repositories.your-org composer https://pricore.yourcompany.com/your-org
```

Then run:

```bash
composer audit
```

Composer will check your project's installed packages against the advisories stored in Pricore. See the [API reference](/api/#security-advisories) for endpoint details.

## Sync Schedule

Advisories are synced automatically every 4 hours alongside mirror syncs. You can also trigger a sync manually:

```bash
# Dispatch a background sync job
php artisan security:sync-advisories

# Run synchronously (useful for debugging)
php artisan security:sync-advisories --sync
```

The first sync fetches all advisories from Packagist. Subsequent syncs are incremental — only advisories updated since the last sync are fetched.

::: tip
After a sync, Pricore automatically re-scans all packages for new matches. Packages are also scanned automatically after every repository sync or mirror sync that adds or updates versions.
:::

## Notifications

When new vulnerabilities are detected, organization owners and admins receive an email with:

- The number of new vulnerabilities found
- A severity breakdown (critical, high, medium, low)
- The top advisory titles
- A link to the security overview page

Notifications are sent per-package as scans complete — if a single advisory sync affects multiple packages, each package triggers its own notification.

## Realtime Updates

When [Laravel Reverb](/getting-started/configuration#realtime-updates-reverb) is configured, vulnerability detection events are pushed to the browser in realtime. The security overview page and package detail pages update automatically when new advisories are matched.
