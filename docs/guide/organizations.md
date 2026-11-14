# Organizations

An organization is a Composer repository with its own packages, repositories, members and access tokens. Nothing is shared between organizations: a token for one organization can't install packages from another.

Most teams need a single organization. Create more when groups should not see each other's packages, for example separate clients.

## Creating an organization

Click **Create Organization** on the dashboard and pick a name. The slug derived from it is part of every URL, including the Composer repository URL:

```
https://pricore.example.com/{slug}
```

The owner can change the slug later under **Organization Settings** > **General**. Every project that uses the old URL in its `composer.json` has to be updated, so pick it with care.

## Roles

| | Member | Admin | Owner |
|---|:---:|:---:|:---:|
| View packages, repositories and the security overview | ✓ | ✓ | ✓ |
| Install packages with a personal token | ✓ | ✓ | ✓ |
| Add, sync and delete repositories, packages and versions | | ✓ | ✓ |
| Upload packages | | ✓ | ✓ |
| Manage members, access tokens, SSH keys, mirrors and security settings | | ✓ | ✓ |
| Change the slug or delete the organization | | | ✓ |

Each organization has one owner, the user who created it. Ownership can't be transferred.

## Members

To invite someone, go to **Organization Settings** > **Members**, click **Invite Member**, enter their email address and choose Admin or Member. They receive a link that is valid for 7 days. If they don't have an account yet, they create one through the link, even when sign-up is disabled.

Admins and the owner can change roles and remove members from the same page. Members can leave an organization themselves under **Settings** > **Organizations**.

When someone is removed, their personal access tokens stop working for the organization immediately. Organization tokens they created keep working, because they belong to the organization. Review those under **Access Tokens** and revoke what they had access to.

## Deleting an organization

The owner can delete the organization under **Organization Settings** > **General**. This removes all its packages, versions, archives, repositories and tokens, and can't be undone.
