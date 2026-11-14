# Packages

A package in Pricore comes from one of three sources:

- **A Git repository.** Pricore reads `composer.json` from every tag and branch. This is how most packages are added; see [Repositories](/guide/repositories).
- **A registry mirror.** Packages imported from another Composer registry, such as a Satis instance. See [Registry Mirrors](/guide/registry-mirrors).
- **An uploaded zip archive.** For code that has no Git repository you can sync from, described below.

A package keeps its source. Repositories and mirrors never take over an uploaded package, and a synced package can't receive uploads.

## Versions

Pricore follows Composer's rules for turning refs into versions:

| Source | Becomes | Example |
|--------|---------|---------|
| Tag `v1.2.0` or `1.2.0` | A release | `1.2.0` |
| Branch `main` | A dev version | `dev-main` |
| Branch `2.x` | A dev version | `2.x-dev` |

Tags that aren't valid versions are ignored. Each version stores the full `composer.json` of its commit, which is what Composer receives.

The package page shows the README, requirements, download counts and [vulnerabilities](/guide/security-auditing) of each version. Admins can delete a single version or the whole package there. For a synced package, also delete the tag or branch, or the version can return on a later sync.

## Installing packages

Add the organization as a repository in your project and authenticate with an [access token](/guide/tokens):

```bash
composer config repositories.acme composer https://pricore.example.com/acme
composer config --global --auth http-basic.pricore.example.com token YOUR_TOKEN
composer require acme/billing
```

Composer reports each install back to Pricore, which is where the download counts on the package page come from.

## Uploading packages

Use uploads for code you only have as an archive, such as a vendor SDK, or for packages whose `composer.json` is generated during a build.

To create an uploaded package, go to **Packages** and click **Upload Package**. Choose a `.zip` with `composer.json` at its root or inside a single top-level directory, and enter a version. You can leave the version empty when `composer.json` has a `version` field. The package name is taken from `composer.json`.

Only admins and the owner can create uploaded packages. New versions can then be uploaded from the package page, or [from CI](#publishing-from-ci).

Pricore serves the archive exactly as uploaded, and Composer checks it against the stored checksum. Some rules apply:

- **Releases can't be changed.** Uploading a different archive for an existing release such as `1.2.0` fails; publish `1.2.1` instead. Uploading the identical archive again succeeds without changing anything, so a retried CI job doesn't fail.
- **Dev versions can be replaced.** A new `dev-main` archive replaces the previous one. The old archive stays downloadable for lock files that still point to it.
- **The size limit is 64 MB** unless your instance [changed it](/getting-started/configuration#package-storage).

### Publishing from CI

Create an [organization token](/guide/tokens) with **Allow publishing packages** enabled, store it as a CI secret, and post the archive:

```bash
curl --fail-with-body \
  -H "Authorization: Bearer $PRICORE_PUBLISH_TOKEN" \
  -F archive=@build/package.zip \
  -F version=1.4.0 \
  https://pricore.example.com/acme/api/packages/upload
```

The [API reference](/api/#upload-a-package-version) lists the responses and errors.

The upload endpoint only adds versions to packages that already exist. A leaked CI token therefore can't introduce a new package, for example one with the name of a public package that your projects would then install from Pricore instead.
