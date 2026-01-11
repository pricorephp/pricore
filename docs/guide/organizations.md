# Organizations

Organizations are the foundation of Pricore's multi-tenant architecture. Each organization has its own packages, repositories, and access tokens, providing complete isolation between teams.

## Creating an Organization

After logging in, you can create a new organization:

1. Click **Create Organization** from the dashboard
2. Enter a name and unique slug (used in URLs)
3. The slug becomes part of your Composer repository URL: `https://packages.yourcompany.com/org/{slug}`

## Organization Structure

Each organization contains:

- **Packages** - Your private Composer packages
- **Repositories** - Connected Git repositories for automatic syncing
- **Access Tokens** - Authentication tokens for Composer clients
- **Members** - Team members with role-based permissions

## Member Roles

Organizations support role-based access control:

| Role | Permissions |
|------|-------------|
| **Owner** | Full control including organization deletion |
| **Admin** | Manage packages, repositories, tokens, and members |
| **Member** | View packages and create personal access tokens |

## Managing Members

### Inviting Members

1. Navigate to **Organization Settings** > **Members**
2. Click **Invite Member**
3. Enter the user's email address
4. Select a role
5. The user receives an invitation email

### Changing Roles

Admins and owners can change member roles:

1. Go to **Organization Settings** > **Members**
2. Find the member and click the role dropdown
3. Select the new role

### Removing Members

1. Go to **Organization Settings** > **Members**
2. Click **Remove** next to the member
3. Confirm the removal

::: warning
Removing a member does not revoke their personal access tokens. You should audit and revoke any tokens they created.
:::

## Organization Settings

### General Settings

- **Name** - Display name for the organization
- **Slug** - URL-friendly identifier (cannot be changed after creation)
- **Description** - Optional description shown on the organization page

### Danger Zone

The organization owner can:

- **Transfer Ownership** - Transfer to another admin
- **Delete Organization** - Permanently delete the organization and all its data

::: danger
Deleting an organization removes all packages, repositories, tokens, and member associations. This action cannot be undone.
:::

## Best Practices

1. **Use descriptive slugs** - Choose slugs that clearly identify your team or project
2. **Minimize owners** - Keep the number of owners small for security
3. **Audit tokens regularly** - Review access tokens and remove unused ones
4. **Use team accounts wisely** - Create separate organizations for different teams or projects
