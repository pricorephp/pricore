# Access Tokens

Access tokens authenticate Composer clients with your Pricore instance. They provide secure, revocable access to your private packages.

## Token Types

Pricore supports two types of tokens:

### Organization Tokens

- Created by organization members
- Grant access only to packages within that organization
- Ideal for CI/CD pipelines and shared access
- Managed in **Organization Settings** > **Composer Tokens**

### Personal Tokens

- Created by individual users
- Grant access across all organizations the user belongs to
- Ideal for local development
- Managed in **Settings** > **Personal Tokens**

## Creating Tokens

### Organization Token

1. Navigate to **Organization Settings** > **Composer Tokens**
2. Click **Create Token**
3. Enter a descriptive name (e.g., "CI Pipeline", "Production Deploy")
4. Select an expiration period (never, 30 days, 90 days, or 1 year)
5. Click **Create**
6. **Copy the token immediately** — it won't be shown again

### Personal Token

1. Go to **Settings** > **Personal Tokens**
2. Click **Create Token**
3. Enter a name for the token
4. Select an expiration period
5. Click **Create**
6. Copy and store the token securely

After creation, a dialog shows the plain token along with a pre-filled Composer command you can copy directly.

## Token Scopes

| Scope | Permission |
|-------|------------|
| `read` | Read-only access to packages |
| `write` | Upload and modify packages |
| `admin` | Administrative access |

## Using Tokens with Composer

When you create a token, Pricore shows the exact Composer command to configure authentication. The format is:

```bash
composer config --global --auth http-basic.packages.yourcompany.com YOUR_ACCESS_TOKEN ""
```

This creates or updates `~/.composer/auth.json`:

```json
{
    "http-basic": {
        "packages.yourcompany.com": {
            "username": "YOUR_ACCESS_TOKEN",
            "password": ""
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
            "username": "YOUR_ACCESS_TOKEN",
            "password": ""
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
export COMPOSER_AUTH='{"http-basic":{"packages.yourcompany.com":{"username":"'"$PRICORE_TOKEN"'","password":""}}}'
```

Or in your CI configuration:

```yaml
# GitHub Actions example
- name: Configure Composer
  run: |
    composer config --global --auth http-basic.packages.yourcompany.com ${{ secrets.PRICORE_TOKEN }} ""
```

## Token Security

### Best Practices

1. **Use descriptive names** — Know what each token is used for
2. **Set expiration dates** — Rotate tokens regularly
3. **Use organization tokens for CI/CD** — Limit access to a single organization
4. **Use personal tokens for development** — Convenient access across all your organizations
5. **Never commit tokens** — Use environment variables or secrets management

### Revoking Tokens

To revoke a token:

1. Go to the token list (Organization Settings or Personal Settings)
2. Find the token to revoke
3. Click **Revoke**
4. Confirm the action

Revoked tokens immediately stop working. Update any systems using the token.

### Token Audit

Monitor token usage:

- **Last Used** — When the token was last used
- **Created** — When the token was created
- **Expires** — When the token will expire (if set)

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
        run: composer config --global --auth http-basic.packages.yourcompany.com ${{ secrets.PRICORE_TOKEN }} ""

      - name: Install dependencies
        run: composer install
```

### GitLab CI

```yaml
install:
  stage: build
  before_script:
    - composer config --global --auth http-basic.packages.yourcompany.com $PRICORE_TOKEN ""
  script:
    - composer install
```

### Bitbucket Pipelines

```yaml
pipelines:
  default:
    - step:
        script:
          - composer config --global --auth http-basic.packages.yourcompany.com $PRICORE_TOKEN ""
          - composer install
```

## Troubleshooting

### Authentication Failed

1. Verify the token is correct (no extra spaces)
2. Check that the token hasn't been revoked or expired
3. Verify the domain in `auth.json` matches your Pricore URL
4. Ensure the token is used as the username (not the password)

### Token Not Working for Specific Package

1. If using an organization token, verify the package belongs to that organization
2. If using a personal token, verify your organization membership
3. Ensure the package exists and is accessible

### Token Expired

1. Check the token's expiration date in your settings
2. Create a new token if expired
3. Update all systems using the old token
