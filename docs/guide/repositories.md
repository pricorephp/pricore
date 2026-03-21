# Repositories

Repositories connect your Git providers to Pricore, enabling automatic package syncing when you push new tags or update branches.

## Supported Providers

Pricore supports multiple Git providers:

| Provider | Features |
|----------|----------|
| **GitHub** | OAuth integration, webhooks, automatic sync |
| **GitLab** | OAuth integration, webhooks, self-hosted support |
| **Generic Git** | SSH key authentication, manual webhooks, works with any Git server |

Generic Git support lets you connect any Git repository accessible via SSH or HTTPS — including self-hosted servers like Gitea, Gogs, Forgejo, or plain Git over SSH.

## Connecting a Repository

### GitHub & GitLab (OAuth)

1. Navigate to **Repositories** > **Add Repository**
2. Select your Git provider (GitHub or GitLab)
3. Select the repository from the list
4. Pricore automatically:
   - Fetches `composer.json` from the default branch
   - Creates a package with the discovered metadata
   - Sets up webhooks for automatic syncing

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

## Webhook Configuration

### Automatic Webhooks (GitHub & GitLab)

For OAuth-connected repositories, webhooks are configured automatically when you connect the repository. Pricore registers a webhook with your Git provider that triggers a sync on push events.

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

1. **Invalid composer.json** - Ensure your repository has a valid `composer.json` at the root
2. **Authentication issues** - Re-authorize the OAuth connection, or verify your SSH key is added as a deploy key
3. **Webhook delivery failed** - Check your Git provider's webhook logs
4. **Rate limiting** - Wait and retry, or check API limits
5. **SSH key not found** - Ensure the repository has an SSH key selected and the key hasn't been deleted

### Missing Versions

If versions aren't appearing:

1. Verify tags follow semver format (e.g., `v1.0.0` or `1.0.0`)
2. Check that `composer.json` exists in the tagged commit
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
