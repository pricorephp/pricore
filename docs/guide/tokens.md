# Access Tokens

Composer authenticates to Pricore with an access token. There are two kinds:

| | Organization token | Personal token |
|---|---|---|
| Created under | **Organization Settings** > **Access Tokens** (admins and owner) | **Settings** > **Access Tokens** (any user) |
| Installs packages from | That organization | Every organization you belong to |
| Stops working when | It is revoked or expires | It is revoked or expires, or for one organization when you leave it |
| Use it for | CI, deployments, servers | Your own machine |

Use organization tokens for anything that isn't a person. They keep working when the colleague who created them leaves.

## Creating a token

Click **Create Token**, give it a name that says where it's used, such as "GitHub Actions", and choose when it expires: never, or after 30 days, 90 days or a year.

The token is shown once, together with the commands to configure Composer. Copy it then; Pricore only stores a hash.

### Permissions

Every token can install packages. You can allow more when you create the token, or later with the edit button in the token list. The token value doesn't change when you edit it.

- **Allow publishing packages** lets the token [upload new versions](/guide/packages#publishing-from-ci) of uploaded packages. A personal token can only publish while its owner is an admin or owner of the organization.
- **API access** grants [REST API](/api/#scopes) permissions, such as reading packages or syncing repositories.

Leave both off unless the token needs them. A token that can only install can't change anything.

## Configuring Composer

Composer sends the token with HTTP basic authentication, with `token` as the username:

```bash
composer config --global --auth http-basic.pricore.example.com token YOUR_TOKEN
```

This writes the token to your global `auth.json`, so it is used by every project on your machine. To configure a single project instead, leave out `--global`. That writes `auth.json` in the project directory, which you should add to `.gitignore`.

### CI

In CI, store the token as a secret and pass it through the `COMPOSER_AUTH` environment variable, which Composer reads without any setup step:

::: code-group

```yaml [GitHub Actions]
- run: composer install
  env:
    COMPOSER_AUTH: '{"http-basic":{"pricore.example.com":{"username":"token","password":"${{ secrets.PRICORE_TOKEN }}"}}}'
```

```yaml [GitLab CI]
install:
  variables:
    COMPOSER_AUTH: '{"http-basic":{"pricore.example.com":{"username":"token","password":"$PRICORE_TOKEN"}}}'
  script:
    - composer install
```

```bash [Shell]
export COMPOSER_AUTH='{"http-basic":{"pricore.example.com":{"username":"token","password":"'"$PRICORE_TOKEN"'"}}}'
composer install
```

:::

## Revoking a token

Click **Revoke** next to the token. It stops working immediately. The token list also shows when each token was last used, which helps to find tokens nobody needs anymore.

You can also create tokens from the command line, for example while provisioning a server:

```bash
php artisan token:create --organization=acme --name="Deploy" --expires=never
```

## Troubleshooting

Composer asks for credentials, or answers `401`:

- The host in `auth.json` must match the host of the repository URL exactly, including any port.
- The token is the password; the username is `token`.
- The token may have expired or been revoked. Check the token list.
- An organization token only works for its own organization. A personal token only works for organizations you are a member of.
