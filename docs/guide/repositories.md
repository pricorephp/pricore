# Repositories

A repository is a Git repository that Pricore reads packages from. Every tag and branch with a `composer.json` becomes a [package version](/guide/packages#versions), and a webhook triggers a new sync when you push.

Adding repositories and everything else on this page requires the Admin or Owner [role](/guide/organizations#roles). Members can see repositories and their sync status.

## Providers

| Provider | Authentication | Webhook |
|----------|----------------|---------|
| GitHub | Your GitHub account or a personal access token | Registered automatically |
| GitLab | Your GitLab.com or self-hosted GitLab account | Registered automatically |
| Bitbucket | An Atlassian API token | Registered automatically |
| Generic Git | An [SSH key](/guide/ssh-keys), or none for public repositories | [Set up by hand](#generic-git-webhooks) |

Use Generic Git for anything else that speaks Git, such as Gitea, Forgejo or a plain SSH server.

## Adding a repository

### GitHub, GitLab and Bitbucket

Pricore talks to these providers with your own credentials. Add them once under **Settings** > **Git Providers**:

- **GitHub:** connect your account, or add a personal access token. A classic token needs the `repo` scope; a fine-grained token needs read access to Contents, and read and write access to Webhooks so Pricore can register the webhook.
- **GitLab:** connect your account, or add a token with the `api` scope. For a self-hosted instance, set the **GitLab URL**.
- **Bitbucket:** add your Atlassian email and an API token created at [Atlassian account settings](https://id.atlassian.com/manage-profile/security/api-tokens) with **Create API token with scopes**. Choose Bitbucket and enable `read:user:bitbucket`, `read:repository:bitbucket`, `read:workspace:bitbucket`, `read:webhook:bitbucket` and `write:webhook:bitbucket`.

Then go to **Repositories**, click **Add Repository**, choose the provider and pick the repository. With several accounts for one provider, you also choose which one to use. **Import Repositories** adds several repositories at once.

Pricore keeps syncing with the account the repository was added with, for everyone in the organization. If that person loses access to the repository or removes the credential, syncs start to fail; see [Repository access errors](#repository-access-errors).

### Generic Git

1. For a private repository, [generate an SSH key](/guide/ssh-keys) and add its public key as a deploy key on the Git server.
2. Go to **Repositories**, click **Add Repository** and choose **Generic Git**.
3. Enter the clone URL, for example `git@git.example.com:acme/billing.git`, and select the SSH key. Leave the key empty for a public HTTPS URL.

#### Git servers on a private network

Pricore refuses Generic Git URLs that resolve to a private, loopback or other reserved IP address. This check runs when the repository is added and on every sync, and also applies to redirects.

If your Git server is on a private network, add its hostname or IP, exactly as it appears in the URL, to `GIT_ALLOWED_PRIVATE_HOSTS`:

```bash
GIT_ALLOWED_PRIVATE_HOSTS=git.internal,10.20.30.40
```

::: warning
An allowed host is reachable on every port by every organization admin through the Generic Git provider, and by every user who enters it as a self-hosted GitLab URL. Only allow hosts that serve Git.
:::

## Monorepos

A repository can hold several packages, each with its own `composer.json` in a subdirectory. List the directories under **Package paths**, when you add the repository or later on its **Edit** page. Each line is a path relative to the repository root:

| Path | Selects |
|------|---------|
| `packages/billing` | That directory |
| `packages/*` | Every directory directly under `packages` |
| `*` | Every directory in the repository root |
| `.` | The repository root |

When package paths are set, the root `composer.json` is only used if you list `.`. In most monorepos the root `composer.json` is for development, not a package to publish, so leaving it out is usually right.

What to expect:

- Every branch and version tag produces a version of each package that exists at that commit. A package added later has no versions at older tags.
- If a package disappears from a branch, its version for that branch is removed. If its `composer.json` is invalid, the existing version stays.
- Composer can't install a subdirectory from Git, so these packages are only installable from [dist archives](/guide/dist-mirroring). A version becomes available once its archive is built. Keep `DIST_ENABLED` on.
- Changing the paths starts a full sync. Packages that no longer match any path are deleted, together with their versions and archives.

### Releasing packages separately

By default, a tag such as `v2.0.0` releases every package in the repository at that version. To release packages on their own, set a **Tag format** on the repository's **Edit** page:

| Format | Example tag | Releases |
|--------|-------------|----------|
| `{package}@{version}` | `billing@1.2.0` | `acme/billing` 1.2.0 |
| `{package}-{version}` | `billing-v1.2.0` | `acme/billing` v1.2.0 |
| `{path}/{version}` | `packages/billing/v1.2.0` | The package in `packages/billing`, v1.2.0 |
| `release/{name}/{version}` | `release/acme/billing/1.2.0` | `acme/billing` 1.2.0 |

`{package}` is the package name without the vendor, `{name}` the full name and `{path}` the package directory. A format contains `{version}`, one of the other three, and at least one character between them. `{package}@{version}` is what Changesets, Lerna and Nx create, and `{package}-{version}` is what release-please creates.

- Plain version tags keep releasing every package, so you can combine both styles. When a package gets the same version from both, its own tag wins.
- Branches are shared: `main` gives every package a `dev-main` version.
- Tags that are neither a version nor in the format are ignored.
- The **Edit** page shows which package and version the repository's recent tags would release before you save.
- Changing the format starts a full sync, and removes versions from tags that no longer match.

## Syncing

A sync reads the tags and branches, adds and updates versions, removes versions whose tag or branch is gone, and builds [dist archives](/guide/dist-mirroring). It runs:

- when the provider sends a webhook after a push,
- when you click **Sync Now** on the repository page,
- when you run `php artisan sync:repository {uuid}`, or `--organization={slug}` or `--all`.

Pricore doesn't sync on a schedule, so without a working webhook, versions only update when you sync by hand.

The repository page shows the status of the last sync, and **Sync History** lists earlier ones with their counts and errors. With [Reverb](/getting-started/configuration#realtime-updates-reverb) configured, the status updates without reloading the page.

### Webhooks

For GitHub, GitLab and Bitbucket, Pricore registers the webhook when you add the repository. If it was deleted or changed on the provider's side, use **Re-register Webhook** on the repository page.

#### Generic Git webhooks

Pricore can't register webhooks on an arbitrary Git server, so you add it yourself:

1. On the repository page, click **Activate Webhook** to get a URL and a secret.
2. Configure your Git server, for example with a Gitea webhook or a `post-receive` hook, to send a `POST` request to the URL after every push.
3. Send the secret as `Authorization: Bearer <secret>`, as an `X-Webhook-Token` header, or as a `?token=` query parameter.

A minimal `post-receive` hook:

```bash
#!/bin/sh
curl -fsS -X POST -H "Authorization: Bearer $PRICORE_WEBHOOK_SECRET" \
  https://pricore.example.com/webhooks/git/{repository-uuid}
```

**Reset Webhook Secret** generates a new secret if the old one leaked.

## Troubleshooting

### Repository access errors

Each sync starts by checking that the credential can read the repository. If it can't, **View error** in the sync history shows what the provider answered. The person who added the repository can fix their credential under **Settings** > **Git Providers**.

| Error | Check |
|-------|-------|
| No token available | That person still has a credential for this provider |
| HTTP 401 | The token hasn't expired or been revoked, and belongs to the right GitLab instance |
| HTTP 403 | The token has the required access, and no IP allowlist or SSO policy blocks it |
| HTTP 404 | The repository path is correct and the account can see it. Providers answer 404 for repositories an account has no access to. On GitLab, at least the Reporter role is needed. |
| Could not connect | The GitLab URL is correct and reachable from Pricore, with a trusted TLS certificate |

For Generic Git, check that the public key is a deploy key on the repository and that the key selected in Pricore still exists.

### A version is missing

- The tag must be a valid version, such as `1.0.0` or `v1.0.0`. Tags like `release-1` are ignored.
- The commit must have a valid `composer.json` (in the package directory, for a monorepo).
- With a [tag format](#releasing-packages-separately), check the preview on the **Edit** page.
- For monorepo packages, the version appears once its dist archive is built. Check Horizon for failed jobs.

### Pushes don't trigger a sync

- Pricore must be reachable from the provider at `APP_URL`. A Pricore instance on `localhost` or an internal network won't receive webhooks from GitHub.com.
- Check the delivery log of the webhook at your provider. A `403` response means the secret doesn't match; re-register the webhook or reset the secret.
