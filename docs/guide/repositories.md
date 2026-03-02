# Repositories

Repositories connect your Git providers to Pricore, enabling automatic package syncing when you push new tags or update branches.

## Supported Providers

Pricore supports multiple Git providers:

| Provider | Features |
|----------|----------|
| **GitHub** | OAuth integration, webhooks, automatic sync |
| **GitLab** | OAuth integration, webhooks, self-hosted support |

## Connecting a Repository

### Via OAuth (Recommended)

1. Navigate to **Repositories** > **Connect Repository**
2. Select your Git provider (GitHub or GitLab)
3. Authorize Pricore to access your repositories
4. Select the repository to connect
5. Pricore automatically:
   - Fetches `composer.json` from the default branch
   - Creates a package with the discovered metadata
   - Sets up webhooks for automatic syncing

## Repository Configuration

### Sync Settings

| Setting | Description |
|---------|-------------|
| **Auto-sync** | Automatically sync when webhooks are received |
| **Sync branches** | Include dev versions from branches |
| **Branch filter** | Only sync specific branches (e.g., `main`, `develop`) |

### Webhook Configuration

For OAuth-connected repositories, webhooks are configured automatically. For manual setup:

**GitHub:**
```
URL: https://pricore.yourcompany.com/webhooks/github/{repository-id}
Content-type: application/json
Events: Push, Create (tags)
```

**GitLab:**
```
URL: https://pricore.yourcompany.com/webhooks/gitlab/{repository-id}
Secret token: (auto-generated when registering via Pricore)
Trigger: Push events, Tag push events
```

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
2. **Authentication issues** - Re-authorize the OAuth connection
3. **Webhook delivery failed** - Check your Git provider's webhook logs
4. **Rate limiting** - Wait and retry, or check API limits

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

## Best Practices

1. **Use OAuth when possible** - Automatic webhook setup and easier management
2. **Enable auto-sync** - Keep packages updated without manual intervention
3. **Tag releases properly** - Use semantic versioning for clear version history
4. **Monitor sync status** - Check failed syncs promptly to avoid stale packages
5. **Secure webhooks** - Use webhook secrets when available
