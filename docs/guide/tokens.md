# Access Tokens

Access tokens authenticate Composer clients with your Pricore instance. They provide secure, revocable access to your private packages.

## Token Types

Pricore supports two types of tokens:

### Organization Tokens

- Created by organization admins
- Can be scoped to specific packages
- Ideal for CI/CD pipelines and shared access

### Personal Tokens

- Created by individual users
- Inherit the user's organization permissions
- Ideal for local development

## Creating Tokens

### Organization Token

1. Navigate to **Organization Settings** > **Access Tokens**
2. Click **Create Token**
3. Enter a descriptive name (e.g., "CI Pipeline", "Production Deploy")
4. Select scopes and package access
5. Set an optional expiration date
6. Click **Create**
7. **Copy the token immediately** - it won't be shown again

### Personal Token

1. Go to **Account Settings** > **Access Tokens**
2. Click **Create Token**
3. Enter a name for the token
4. Set an optional expiration date
5. Click **Create**
6. Copy and store the token securely

## Token Scopes

| Scope | Permission |
|-------|------------|
| `packages:read` | Download packages |
| `packages:write` | Upload and modify packages |
| `packages:delete` | Delete packages and versions |

## Package-Level Access

Organization tokens can be restricted to specific packages:

1. When creating a token, select **Limit to specific packages**
2. Choose which packages this token can access
3. The token will only work for selected packages

This is useful for:
- CI pipelines that only need access to certain packages
- Third-party integrations with minimal permissions
- Temporary access for contractors

## Using Tokens with Composer

### Global Configuration

Configure Composer globally to use your token:

```bash
composer config --global --auth http-basic.packages.yourcompany.com token YOUR_ACCESS_TOKEN
```

This creates or updates `~/.composer/auth.json`:

```json
{
    "http-basic": {
        "packages.yourcompany.com": {
            "username": "token",
            "password": "YOUR_ACCESS_TOKEN"
        }
    }
}
```

### Project Configuration

For project-specific tokens, create `auth.json` in your project root:

```json
{
    "http-basic": {
        "packages.yourcompany.com": {
            "username": "token",
            "password": "YOUR_ACCESS_TOKEN"
        }
    }
}
```

::: warning
Add `auth.json` to your `.gitignore` to avoid committing tokens to version control.
:::

### Environment Variables

For CI/CD, use environment variables:

```bash
export COMPOSER_AUTH='{"http-basic":{"packages.yourcompany.com":{"username":"token","password":"'"$PRICORE_TOKEN"'"}}}'
```

Or in your CI configuration:

```yaml
# GitHub Actions example
- name: Configure Composer
  run: |
    composer config --global --auth http-basic.packages.yourcompany.com token ${{ secrets.PRICORE_TOKEN }}
```

## Token Security

### Best Practices

1. **Use descriptive names** - Know what each token is used for
2. **Set expiration dates** - Rotate tokens regularly
3. **Minimize scope** - Only grant necessary permissions
4. **Limit package access** - Restrict tokens to required packages
5. **Never commit tokens** - Use environment variables or secrets management

### Revoking Tokens

To revoke a token:

1. Go to the token list (Organization or Personal settings)
2. Find the token to revoke
3. Click **Revoke**
4. Confirm the action

Revoked tokens immediately stop working. Update any systems using the token.

### Token Audit

Monitor token usage:

- **Last Used** - When the token was last used
- **Created** - When the token was created
- **Expires** - When the token will expire (if set)

Regular audits help identify:
- Unused tokens that should be revoked
- Tokens used more frequently than expected
- Tokens approaching expiration

## CI/CD Integration

### GitHub Actions

```yaml
name: Install Dependencies

on: [push]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Configure Pricore
        run: composer config --global --auth http-basic.packages.yourcompany.com token ${{ secrets.PRICORE_TOKEN }}

      - name: Install dependencies
        run: composer install
```

### GitLab CI

```yaml
install:
  stage: build
  before_script:
    - composer config --global --auth http-basic.packages.yourcompany.com token $PRICORE_TOKEN
  script:
    - composer install
```

### Bitbucket Pipelines

```yaml
pipelines:
  default:
    - step:
        script:
          - composer config --global --auth http-basic.packages.yourcompany.com token $PRICORE_TOKEN
          - composer install
```

## Troubleshooting

### Authentication Failed

1. Verify the token is correct (no extra spaces)
2. Check that the token hasn't been revoked
3. Ensure the token has appropriate scopes
4. Verify the domain in `auth.json` matches your Pricore URL

### Token Not Working for Specific Package

1. Check package-level restrictions on the token
2. Verify the organization membership
3. Ensure the package exists and is accessible

### Token Expired

1. Check the token's expiration date
2. Create a new token if expired
3. Update all systems using the old token
