# Repositories

Repositories connect your Git providers to Pricore, enabling automatic package syncing when you push new tags or update branches.

## Supported Providers

Pricore supports multiple Git providers:

| Provider | Features |
|----------|----------|
| **GitHub** | OAuth integration, webhooks, automatic sync |
| **GitLab** | OAuth integration, webhooks, self-hosted support |
| **Bitbucket** | API token authentication, webhooks, automatic sync |
| **Generic Git** | SSH key authentication, manual webhooks, works with any Git server |

Generic Git support lets you connect any Git repository accessible via SSH or HTTPS — including self-hosted servers like Gitea, Gogs, Forgejo, or plain Git over SSH.

## Connecting a Repository

### GitHub & GitLab (OAuth)

1. Navigate to **Repositories** > **Add Repository**
2. Select your Git provider (GitHub or GitLab)
3. Select the repository from the list
4. Pricore automatically:
   - Reads `composer.json` from every tag and branch
   - Creates a package for each `composer.json` it finds (the root one, unless you configure [package paths](#monorepos))
   - Sets up webhooks for automatic syncing

### Bitbucket (API Token)

1. Go to **Settings** > **Git Providers** and add your Bitbucket credentials (Atlassian account email + API token)
2. Navigate to **Repositories** > **Add Repository**
3. Select **Bitbucket** as the provider
4. Select the workspace and repository from the list
5. Pricore automatically sets up webhooks and syncs the repository

::: tip
Create a scoped API token at [Atlassian Account Settings > API tokens](https://id.atlassian.com/manage-profile/security/api-tokens). Select **Bitbucket** as the app and enable: `read:user:bitbucket`, `read:repository:bitbucket`, `read:workspace:bitbucket`, `read:webhook:bitbucket`, and `write:webhook:bitbucket`.
:::

### Generic Git

1. [Generate an SSH key](/guide/ssh-keys) in your organization settings (for private repos)
2. Add the public key as a deploy key on your Git server
3. Navigate to **Repositories** > **Add Repository**
4. Select **Generic Git** as the provider
5. Enter the repository URL (e.g., `git@github.com:acme/billing.git`)
6. Select the SSH key to use for authentication (optional for public repos)
7. Pricore syncs the repository and discovers packages

::: tip
Public repositories accessible over HTTPS don't require an SSH key. Just enter the HTTPS URL and leave the SSH key unselected.
:::

## Monorepos

A single repository can hold several Composer packages, each with its own `composer.json` in a subdirectory. Tell Pricore where to look with **package paths**, either when connecting the repository or later from the repository's **Edit** page.

Each line is a directory relative to the repository root:

| Pattern | Selects |
|---------|---------|
| `packages/billing` | That directory |
| `packages/*` | Every directory directly under `packages` |
| `*` | Every directory at the repository root |
| `.` | The repository root itself |

When package paths are configured, the root `composer.json` is only synced if `.` is listed. Most monorepos keep a root `composer.json` that describes the repository rather than a publishable package, so leaving it out is usually right.

How monorepo packages behave:

- Every branch, and every version tag such as `v1.2.0`, is read once per package and produces that version for each package present at it. A package that does not exist yet at an older tag has no version there.
- A package that disappears from a branch loses that branch's version; its other versions are kept. A `composer.json` that fails to parse leaves the existing version untouched.
- Packages read from a subdirectory are installed from Pricore's dist archives only, because Composer cannot check out a subdirectory from Git. A version is offered to Composer once its archive is built, so keep [dist archives](/guide/dist-mirroring) enabled for these packages.
- Saving different package paths starts a full sync, or queues one if a sync is already running. Packages with no versions left under the selected paths are removed from Pricore together with their versions and archives. A package that moved directories keeps the versions that are still selected.

### Releasing Packages Separately

To version packages independently, set a **tag format** on the repository's **Edit** page. A tag in that format releases only the package it names, at the version that follows:

| Format | Tag | Releases |
|--------|-----|----------|
| `{package}@{version}` | `billing@1.2.0` | `acme/billing` at `1.2.0` |
| `{path}/{version}` | `packages/billing/v1.2.0` | the package in `packages/billing` at `v1.2.0` |
| `{package}-{version}` | `billing-v1.2.0` | `acme/billing` at `v1.2.0` |
| `release/{name}/{version}` | `release/acme/billing/1.2.0` | `acme/billing` at `1.2.0` |

A format holds `{version}` and one of these placeholders, with at least one character between them:

| Placeholder | Stands for |
|-------------|------------|
| `{package}` | The Composer name without the vendor, such as `billing` |
| `{name}` | The full Composer name, such as `acme/billing` |
| `{path}` | The package directory, such as `packages/billing` |

These conventions match common release tools: `{package}@{version}` is what Changesets, Lerna and Nx create, `{package}-{version}` matches release-please, and `{path}/{version}` is the Go modules style.

- Plain version tags such as `v2.0.0` keep releasing every package, so lockstep and independent releases can be combined. When a package gets the same version from both, its own tag wins.
- Branches stay shared: `main` produces `dev-main` for every package.
- Tags that are neither a version nor in the format are ignored.
- The edit page previews which package and version the repository's recent tags release before you save.
- Saving a different format starts a full sync. Versions created from tags that no longer fit are removed.
- The format can also be set through the [REST API](/api/) with the `tag_format` field when updating a repository.

## Webhook Configuration

### Automatic Webhooks (GitHub, GitLab & Bitbucket)

For OAuth-connected (GitHub/GitLab) or API token-connected (Bitbucket) repositories, webhooks are configured automatically when you connect the repository. Pricore registers a webhook with your Git provider that triggers a sync on push events.

You can re-register a webhook from the repository page via **Actions** > **Re-register Webhook** if it gets out of sync.

### Manual Webhooks (Generic Git)

Generic Git repositories support webhooks, but since there's no standard API for automatic registration, you configure them manually:

1. Go to the repository page
2. Click **Actions** > **Activate Webhook**
3. Pricore generates a webhook URL and secret
4. Configure your Git server to send a POST request to the webhook URL on push events

**Authentication** — include the secret using one of these methods:

| Method | Example |
|--------|---------|
| Bearer token | `Authorization: Bearer YOUR_SECRET` |
| Custom header | `X-Webhook-Token: YOUR_SECRET` |
| Query parameter | `?token=YOUR_SECRET` |

**GitHub webhook format:**
```
URL: https://pricore.yourcompany.com/webhooks/github/{repository-id}
Content-type: application/json
Events: Push, Create (tags)
```

**GitLab webhook format:**
```
URL: https://pricore.yourcompany.com/webhooks/gitlab/{repository-id}
Secret token: (auto-generated when registering via Pricore)
Trigger: Push events, Tag push events
```

**Bitbucket webhook format:**
```
URL: https://pricore.yourcompany.com/webhooks/bitbucket/{repository-id}
Signature: X-Hub-Signature (HMAC-SHA256, auto-configured)
Events: repo:push, repo:refs_changed
```

**Generic Git webhook format:**
```
URL: https://pricore.yourcompany.com/webhooks/git/{repository-id}
Method: POST
Authentication: Bearer token, X-Webhook-Token header, or ?token= query parameter
```

You can reset the webhook secret at any time via **Actions** > **Reset Webhook Secret**.

## Sync Status

Each repository shows its sync status:

| Status | Description |
|--------|-------------|
| **OK** | Last sync completed successfully |
| **Pending** | Sync is queued or in progress |
| **Failed** | Last sync encountered an error |

### Viewing Sync History

1. Go to the repository page
2. Click **Sync History**
3. View logs for each sync attempt

### Realtime Status Updates

When [Laravel Reverb](/getting-started/configuration#realtime-updates-reverb) is configured, sync status updates are pushed to the browser in realtime via WebSockets. All connected users see status changes immediately — no manual refresh needed. This works for both manual syncs and webhook-triggered syncs.

### Manual Sync

To manually trigger a sync:

1. Go to the repository page
2. Click **Sync Now**
3. Pricore fetches all tags and branches, updating package versions

## Repository Permissions

Repository actions require appropriate organization roles:

| Action | Required Role |
|--------|---------------|
| View repositories | Member |
| Connect repository | Admin |
| Edit settings | Admin |
| Delete repository | Admin |
| Trigger manual sync | Admin |

## Troubleshooting

### Sync Failures

Common causes of sync failures:

1. **Invalid composer.json** - Ensure your repository has a valid `composer.json` at the root, or in each configured [package path](#monorepos)
2. **Repository access** - See [Repository Access Errors](#repository-access-errors), or verify your SSH key is added as a deploy key
3. **Webhook delivery failed** - Check your Git provider's webhook logs
4. **Rate limiting** - Wait and retry, or check API limits
5. **SSH key not found** - Ensure the repository has an SSH key selected and the key hasn't been deleted

### Repository Access Errors

Pricore checks access at the start of each sync, using the Git credentials of the person who added the repository. If the check fails, **View error** in the sync history says what the provider answered. They can update the credentials under **Settings > Git Providers**.

| Answer | What to check |
|--------|---------------|
| No token available | Credentials exist for this provider, for the person who added the repository |
| HTTP 401 | The token has not expired or been revoked, and belongs to this GitLab instance |
| HTTP 403 | The token has the required scope (`api` on GitLab, `repo` on GitHub), and no IP allowlist or SSO policy blocks it |
| HTTP 404 | The repository path is correct and the account can see it (at least Reporter on GitLab). Providers answer 404 for repositories an account cannot access |
| Could not connect | The GitLab URL is correct, and the instance is reachable from Pricore with a trusted TLS certificate |

For a self-hosted GitLab instance, set the **GitLab URL** in the credentials. Without it, Pricore connects to GitLab.com.

### Missing Versions

If versions aren't appearing:

1. Verify tags follow semver format (e.g., `v1.0.0` or `1.0.0`)
2. Check that `composer.json` exists in the tagged commit (for monorepos, inside the package directory)
3. Trigger a manual sync
4. Review sync logs for errors

### Webhook Not Triggering

1. Verify the webhook URL is correct
2. Check that the webhook is active in your Git provider
3. Ensure your Pricore instance is publicly accessible
4. Review webhook delivery logs in your Git provider
5. For Generic Git, verify the secret is included in the request

## Best Practices

1. **Use OAuth when possible** - Automatic webhook setup and easier management
2. **Use SSH keys for Generic Git** - More secure than HTTPS for private repositories
3. **Enable auto-sync** - Keep packages updated without manual intervention
4. **Tag releases properly** - Use semantic versioning for clear version history
5. **Monitor sync status** - Check failed syncs promptly to avoid stale packages
6. **Secure webhooks** - Use webhook secrets when available
