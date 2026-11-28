# Security Auditing

Pricore downloads the security advisories that [Packagist publishes](https://packagist.org/apidoc#list-security-advisories) and checks your packages against them. It also serves those advisories to `composer audit`.

## What gets flagged

Each package version is checked in two ways:

- **Direct:** the package itself is named in an advisory, and the version falls in the affected range. This applies to packages you mirror from elsewhere, and to your own packages if they are published on Packagist.
- **Dependency:** a package in `require` or `require-dev` has an advisory, and the version constraint allows an affected version.

A dependency match says that a project *could* end up with a vulnerable version, not that it does. `"guzzlehttp/guzzle": "^7.0"` matches an advisory for `7.4.0` even though Composer would install a fixed release. Run `composer audit` in the project to see what is actually installed.

## Where to see results

The **Security** page of an organization lists vulnerable packages with counts per severity, and can be filtered by severity. It only looks at the latest stable release and the dev versions of each package, because older releases are rarely what's being worked on.

On a package page, every version shows a badge with its highest severity. Open a version to see each advisory with its CVE, severity, a link to the disclosure and, for dependency matches, the dependency that caused it.

Owners and admins get an email when a scan finds new vulnerabilities in a package.

## Settings

Under **Organization Settings** > **Security**, admins and the owner can turn off scanning, or only the emails. Both are on by default.

## When scans run

- Pricore fetches new advisories every 4 hours. The first run downloads all of them; later runs only fetch changes.
- After every advisory update, all packages are scanned again.
- After a repository or mirror sync, the new and changed versions are scanned.
- **Scan Now** on the Security page scans the organization immediately.

To fetch advisories by hand:

```bash
php artisan security:sync-advisories         # as a background job
php artisan security:sync-advisories --sync  # in the foreground
```

## composer audit

Pricore's `packages.json` tells Composer where to find advisories, so `composer audit` checks packages installed from Pricore without any configuration. Packages installed from other repositories, such as Packagist, are checked against those repositories as usual.

```bash
composer audit
```

See the [API reference](/api/#security-advisories) for the endpoint.
