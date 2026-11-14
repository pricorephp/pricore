# SSH Keys

[Generic Git repositories](/guide/repositories#generic-git) that aren't public need an SSH key. Pricore generates the key pair; you add the public key to your Git server as a deploy key.

SSH keys belong to the organization and are managed by admins and the owner under **Organization Settings** > **SSH Keys**. GitHub, GitLab and Bitbucket repositories don't use them; they use the [Git credentials](/guide/repositories#github-gitlab-and-bitbucket) of the person who added them.

## Generating a key

Click **Generate SSH Key** and give it a name that says where it is used, such as "Gitea". Pricore creates an Ed25519 key pair and shows the public key with its fingerprint.

Add the public key as a read-only deploy key on each repository Pricore should read. Pricore never writes to repositories, so don't grant write access. On most servers the setting is under the repository's **Settings** > **Deploy Keys**; on Bitbucket it's called **Access keys**.

When you [add the repository](/guide/repositories#generic-git), select the key.

## Deleting a key

Deleting a key removes it from every repository that uses it, and those repositories stop syncing. An existing repository can't be switched to another key, so check which repositories use a key before you delete it.

## How keys are stored

Private keys are encrypted with `APP_KEY` and never shown in the interface. During a sync, the key is written to a temporary file for the `git` command and deleted afterwards.
