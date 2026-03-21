# SSH Keys

SSH keys let Pricore authenticate with private Git repositories over SSH. Each organization can manage multiple SSH keys and assign them to specific repositories.

## Generating an SSH Key

1. Navigate to **Organization Settings** > **SSH Keys**
2. Click **Generate SSH Key**
3. Enter a name for the key (e.g., "Gitea Deploy Key" or "Internal GitLab")
4. Pricore generates an Ed25519 key pair

The public key is displayed immediately — copy it and add it as a deploy key on your Git server.

## Adding the Deploy Key

After generating an SSH key, add the public key as a read-only deploy key on your Git server:

### Gitea / Forgejo

1. Go to your repository **Settings** > **Deploy Keys**
2. Click **Add Deploy Key**
3. Paste the public key
4. Save

### GitHub

1. Go to your repository **Settings** > **Deploy keys**
2. Click **Add deploy key**
3. Paste the public key
4. Leave "Allow write access" unchecked
5. Click **Add key**

### GitLab

1. Go to your project **Settings** > **Repository** > **Deploy keys**
2. Click **Expand**
3. Paste the public key
4. Ensure "Grant write permissions" is unchecked
5. Click **Add key**

### Bitbucket

1. Go to your repository **Settings** > **Access keys**
2. Click **Add key**
3. Paste the public key
4. Click **Add key**

::: tip
Deploy keys grant read-only access to a single repository. If you need to access multiple repositories on the same server, add the same public key to each one.
:::

## Using SSH Keys with Repositories

When [adding a Generic Git repository](/guide/repositories#generic-git), you can select which SSH key to use from a dropdown. The key is used for all git operations: listing tags and branches, cloning, and fetching updates.

- **Private repositories** require an SSH key — select one when adding the repo
- **Public repositories** over HTTPS don't need a key — leave the dropdown unselected

## Managing Keys

### Viewing Keys

The SSH Keys settings page shows all keys for your organization:

- **Name** — the label you gave the key
- **Fingerprint** — the SHA256 fingerprint for identification
- **Public key** — click "Show Public Key" to reveal and copy

### Deleting a Key

1. Click the delete icon on the key card
2. Confirm the deletion

::: warning
Deleting an SSH key disconnects it from all repositories that use it. Those repositories will fail to sync until you assign a new key or the repository is made publicly accessible.
:::

## Permissions

SSH key management requires the **Admin** or **Owner** role in the organization. Regular members can view repositories but cannot generate or delete SSH keys.

## Security

- Private keys are encrypted at rest in the database using Laravel's encryption
- Private keys are never displayed in the UI — only the public key and fingerprint are shown
- During git operations, the private key is written to a temporary file, used, and immediately deleted
- Keys use the Ed25519 algorithm, which provides strong security with small key sizes
